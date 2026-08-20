<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Cache;

use n5s\DtcgTokens\Loader\CacheableTokenLoaderInterface;
use n5s\DtcgTokens\Parser\TokenMetadata;
use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\TokenValueInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CachedTokenFactory
{
    private const string CACHE_KEY_PREFIX = 'n5s_dtcg_tokens.';

    /**
     * Version segment of the cache key, kept in sync with the released
     * version by release-please (see `extra-files` in
     * release-please-config.json) — do not edit it by hand.
     *
     * The cached payload's shape is the value objects' private property
     * layout, since they are serialized as-is. Tying the segment to the
     * release means an entry can never be read by a different release, so a
     * pool that survives deploys (Redis, APCu) cannot unserialize stale
     * objects into changed classes. The cost is one re-parse per release,
     * including releases that leave the payload untouched — cheap next to
     * having to notice a shape change by hand.
     *
     * Releases before 2.0.1 used a manual "vN" counter, which cannot collide
     * with a version-shaped segment.
     */
    private const string CACHE_VERSION = '2.0.1'; // x-release-please-version

    private ?Tokens $tokens = null;

    /**
     * Source revision the in-process memo was built from; lets debug mode
     * drop the memo when sources change under a long-running worker.
     */
    private ?string $tokensRevision = null;

    /**
     * Whether the last create() that reached the write path stored its result.
     */
    private ?bool $cacheWritten = null;

    /**
     * @param ?int $ttl Lifetime in seconds for pool entries; null (default)
     *                  keeps entries until externally evicted. Set one when
     *                  the pool survives deploys (Redis, APCu) so a release
     *                  cannot serve last release's tokens forever.
     */
    public function __construct(
        private readonly CacheableTokenLoaderInterface $loader,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly bool $debug = false,
        private readonly ?TokenParser $parser = null,
        private readonly ?int $ttl = null,
    ) {
    }

    /**
     * The PSR-6 key under which this factory caches its parsed tokens. Derived
     * from the source files, so factories over different files never collide
     * when they share a pool. Exposed so callers can invalidate it.
     */
    public function cacheKey(): string
    {
        // Hash the fingerprint: a loader is free to return a URL or a DSN,
        // and PSR-6 reserves {}()/\@: in keys — an unhashed one would make
        // every pool call throw, silently disabling the cache for good.
        return self::CACHE_KEY_PREFIX . self::CACHE_VERSION . '.' . hash('xxh128', $this->loader->fingerprint());
    }

    public function create(): Tokens
    {
        if ($this->tokens !== null) {
            // In debug, the in-process memo must not outlive a source edit —
            // long-running workers (FrankenPHP, RoadRunner) would otherwise
            // never see changes after the first call. An UNKNOWN revision
            // (null, per the loader contract) is treated as always stale.
            if (! $this->debug || ($this->tokensRevision !== null && $this->tokensRevision === $this->loader->revision())) {
                return $this->tokens;
            }

            $this->tokens = null;
        }

        // Read the source revision only when the freshness check needs it:
        // the production hit path must serve the pool entry without touching
        // the source backend at all.
        $revision = $this->debug ? $this->loader->revision() : null;

        $item = null;
        if ($this->cache !== null) {
            try {
                $item = $this->cache->getItem($this->cacheKey());
            } catch (\Throwable) {
                // The pool is an optimization, not a dependency: an
                // unreachable backend must not take token resolution down
                // with it. Without an item there is nothing to write either —
                // but that is a FAILED write, not an unattempted one, or an
                // unreachable pool would be indistinguishable from no pool.
                $item = null;
                $this->cacheWritten = false;
            }
        }

        if ($item !== null) {
            try {
                if ($item->isHit()) {
                    $cached = $item->get();
                    // A corrupted, truncated or foreign entry behaves as a
                    // miss instead of surfacing as an opaque TypeError far
                    // from this boundary — and is overwritten below, so a
                    // poisoned entry cannot survive.
                    if (
                        $this->isValidPayload($cached)
                        && (! $this->debug || ($revision !== null && $cached['revision'] === $revision))
                    ) {
                        $this->tokensRevision = $cached['revision'];

                        return $this->tokens = new Tokens($cached['values'], $cached['metadata']);
                    }
                }
            } catch (\Throwable) {
                // Unserializing the payload failed; fall through to a fresh
                // parse and overwrite the entry.
            }
        }

        $revision ??= $this->loader->revision();
        $this->tokens = Tokens::fromArray($this->loader->load(), $this->parser);
        $this->tokensRevision = $revision;

        if ($item !== null) {
            try {
                $item->set([
                    'revision' => $revision,
                    'values' => $this->tokens->all(),
                    'metadata' => $this->tokens->allMetadata(),
                ]);
                if ($this->ttl !== null) {
                    $item->expiresAfter($this->ttl);
                }

                // PSR-6 save() reports failure by returning false rather than
                // throwing (an oversized payload, a full backend), so both
                // outcomes are recorded: the parse already succeeded, and a
                // failed write only costs the next request a re-parse.
                $this->cacheWritten = $this->cache?->save($item) ?? false;
            } catch (\Throwable) {
                $this->cacheWritten = false;
            }
        }

        return $this->tokens;
    }

    /**
     * Whether the parsed tokens were successfully stored: true on a
     * successful write, false when the pool rejected or failed it, null when
     * no write was attempted (no pool, or the result came from the cache).
     *
     * Pool failures are deliberately non-fatal, so this is the only way to
     * notice a pool that never accepts a write — worth asserting in a smoke
     * test or a health check.
     */
    public function cacheWritten(): ?bool
    {
        return $this->cacheWritten;
    }

    /**
     * Runtime shape check for a cached payload. This is a robustness guard
     * against corrupted, truncated or foreign entries — NOT a security
     * boundary: by the time it runs, $item->get() has already unserialized
     * the graph. The pool itself must be trusted (see SECURITY.md).
     *
     * @phpstan-assert-if-true array{revision: ?string, values: array<string, TokenValueInterface>, metadata: array<string, TokenMetadata>} $cached
     */
    private function isValidPayload(mixed $cached): bool
    {
        return \is_array($cached)
            && \array_key_exists('revision', $cached)
            && ($cached['revision'] === null || \is_string($cached['revision']))
            && \array_key_exists('values', $cached) && \is_array($cached['values'])
            && \array_key_exists('metadata', $cached) && \is_array($cached['metadata'])
            // Paths are array keys, so a numeric one ("4") arrives as an int.
            && array_all(
                $cached['values'],
                static fn (mixed $value): bool => $value instanceof TokenValueInterface,
            )
            && array_all(
                $cached['metadata'],
                static fn (mixed $metadata): bool => $metadata instanceof TokenMetadata,
            );
    }
}
