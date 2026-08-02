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
     * Version segment of the cache key. The cached payload's shape is the
     * value objects' private property layout (they are serialized as-is), so
     * bump this whenever that layout — or parsing semantics — changes:
     * pools that survive deploys (Redis, APCu) then miss instead of
     * unserializing stale objects into the new classes.
     */
    private const string CACHE_VERSION = 'v2';

    private ?Tokens $tokens = null;

    /**
     * Source mtime the in-process memo was built from; lets debug mode drop
     * the memo when sources change under a long-running worker.
     */
    private ?int $tokensMtime = null;

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
        return self::CACHE_KEY_PREFIX . self::CACHE_VERSION . '.' . $this->loader->fingerprint();
    }

    public function create(): Tokens
    {
        if ($this->tokens !== null) {
            // In debug, the in-process memo must not outlive a source edit —
            // long-running workers (FrankenPHP, RoadRunner) would otherwise
            // never see changes after the first call. An UNKNOWN mtime (0,
            // per the loader contract) is treated as always stale: 0 === 0
            // is not evidence of freshness.
            if (! $this->debug || ($this->tokensMtime !== 0 && $this->tokensMtime === $this->loader->maxMtime())) {
                return $this->tokens;
            }

            $this->tokens = null;
        }

        // Stat sources only when the freshness check needs them: the
        // production hit path must serve the pool entry without touching disk.
        $maxMtime = $this->debug ? $this->loader->maxMtime() : null;

        $item = null;
        if ($this->cache !== null) {
            try {
                $item = $this->cache->getItem($this->cacheKey());
                if ($item->isHit()) {
                    $cached = $item->get();
                    // A corrupted, truncated or foreign entry behaves as a
                    // miss (and gets overwritten below) instead of surfacing
                    // as an opaque TypeError far from this boundary.
                    if (
                        $this->isValidPayload($cached)
                        && (! $this->debug || ($maxMtime !== 0 && $cached['mtime'] === $maxMtime))
                    ) {
                        $this->tokensMtime = $cached['mtime'];

                        return $this->tokens = new Tokens($cached['values'], $cached['metadata']);
                    }
                }
            } catch (\Throwable) {
                // The pool is an optimization, not a dependency: an
                // unreachable backend must not take token resolution down
                // with it. Skip the write too — it just proved unreliable.
                $item = null;
            }
        }

        $maxMtime ??= $this->loader->maxMtime();
        $this->tokens = Tokens::fromArray($this->loader->load(), $this->parser);
        $this->tokensMtime = $maxMtime;

        if ($item !== null) {
            try {
                $item->set([
                    'mtime' => $maxMtime,
                    'values' => $this->tokens->all(),
                    'metadata' => $this->tokens->allMetadata(),
                ]);
                if ($this->ttl !== null) {
                    $item->expiresAfter($this->ttl);
                }

                $this->cache?->save($item);
            } catch (\Throwable) {
                // The parse already succeeded; a failed write only costs the
                // next request a re-parse.
            }
        }

        return $this->tokens;
    }

    /**
     * Runtime shape check for a cached payload — entries are only trusted
     * after they prove they were written by self::create().
     *
     * @phpstan-assert-if-true array{mtime: int, values: array<string, TokenValueInterface>, metadata: array<string, TokenMetadata>} $cached
     */
    private function isValidPayload(mixed $cached): bool
    {
        return \is_array($cached)
            && \array_key_exists('mtime', $cached) && \is_int($cached['mtime'])
            && \array_key_exists('values', $cached) && \is_array($cached['values'])
            && \array_key_exists('metadata', $cached) && \is_array($cached['metadata'])
            && array_all(
                $cached['values'],
                static fn (mixed $value, mixed $path): bool => \is_string($path) && $value instanceof TokenValueInterface,
            )
            && array_all(
                $cached['metadata'],
                static fn (mixed $metadata, mixed $path): bool => \is_string($path) && $metadata instanceof TokenMetadata,
            );
    }
}
