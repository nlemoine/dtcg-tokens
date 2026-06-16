<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Cache;

use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Parser\TokenMetadata;
use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\TokenValueInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CachedTokenFactory
{
    private const string CACHE_KEY_PREFIX = 'n5s_dtcg_tokens.';

    private ?Tokens $tokens = null;

    public function __construct(
        private readonly JsonFileLoader $loader,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly bool $debug = false,
        private readonly ?TokenParser $parser = null,
    ) {
    }

    /**
     * The PSR-6 key under which this factory caches its parsed tokens. Derived
     * from the source files, so factories over different files never collide
     * when they share a pool. Exposed so callers can invalidate it.
     */
    public function cacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . $this->loader->fingerprint();
    }

    public function create(): Tokens
    {
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        $maxMtime = $this->loader->maxMtime();

        if ($this->cache !== null) {
            $item = $this->cache->getItem($this->cacheKey());
            if ($item->isHit()) {
                // Cache contents are trusted: written only by self::create() below,
                // so the shape is guaranteed. The @var narrows the mixed payload.
                /** @var array{mtime: int, values: array<string, TokenValueInterface>, metadata: array<string, TokenMetadata>} $cached */
                $cached = $item->get();
                if (! $this->debug || $cached['mtime'] === $maxMtime) {
                    return $this->tokens = new Tokens($cached['values'], $cached['metadata']);
                }
            }
        }

        $this->tokens = Tokens::fromArray($this->loader->load(), $this->parser);

        if ($this->cache !== null) {
            $item = $this->cache->getItem($this->cacheKey());
            $item->set([
                'mtime' => $maxMtime,
                'values' => $this->tokens->all(),
                'metadata' => $this->tokens->allMetadata(),
            ]);
            $this->cache->save($item);
        }

        return $this->tokens;
    }
}
