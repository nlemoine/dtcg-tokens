<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Loader;

/**
 * A token loader whose source set can be fingerprinted and freshness-checked,
 * making it usable behind {@see \n5s\DtcgTokens\Cache\CachedTokenFactory}.
 */
interface CacheableTokenLoaderInterface extends TokenLoaderInterface
{
    /**
     * The most recent modification time across the loader's sources, or 0
     * when unknown. Used in debug mode to invalidate stale cache entries;
     * an unknown mtime is treated as ALWAYS STALE (freshness cannot be
     * proven), never as fresh.
     */
    public function maxMtime(): int;

    /**
     * A stable identifier for this loader's source set, for use as a
     * cache-key component: two loaders over the same sources share it,
     * different sources produce different values.
     */
    public function fingerprint(): string;
}
