<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Cache;

use n5s\DtcgTokens\Loader\CacheableTokenLoaderInterface;

/**
 * Test double: counts source accesses and lets tests mutate the source
 * content/mtime, simulating file edits under a long-running process.
 */
final class CountingLoader implements CacheableTokenLoaderInterface
{
    public int $loadCalls = 0;

    public int $revisionCalls = 0;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public array $raw,
        public ?string $revision = '1000',
        private readonly string $fingerprint = 'counting',
    ) {
    }

    public function load(): array
    {
        $this->loadCalls++;

        return $this->raw;
    }

    public function revision(): ?string
    {
        $this->revisionCalls++;

        return $this->revision;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }
}
