<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Cache;

use Psr\Cache\CacheItemInterface;

/**
 * Test double: a cache item that reports a hit but blows up on read, the
 * way a real backend does when its stored payload cannot be unserialized
 * (a truncated write, or a class that no longer exists).
 */
final class UnreadableItem implements CacheItemInterface
{
    public mixed $written = null;

    public function __construct(
        private readonly string $key,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        throw new \UnexpectedValueException('payload could not be unserialized');
    }

    public function isHit(): bool
    {
        return true;
    }

    public function set(mixed $value): static
    {
        $this->written = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
