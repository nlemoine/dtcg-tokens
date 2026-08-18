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
     * An opaque revision of the loader's current sources — an mtime, an
     * ETag, a content hash, a MAX(updated_at) — or null when unknown. Used
     * in debug mode to invalidate stale cache entries: two equal non-null
     * revisions mean "unchanged"; null means freshness cannot be proven and
     * is treated as ALWAYS STALE, never as fresh.
     */
    public function revision(): ?string;

    /**
     * A stable identifier for this loader's source set, for use as a
     * cache-key component: two loaders over the same sources share it,
     * different sources produce different values.
     */
    public function fingerprint(): string;
}
