<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Test double: a PSR-6 pool whose read and/or write side can be made to
 * throw, simulating an unreachable backend (Redis down, network partition).
 */
final class FlakyPool implements CacheItemPoolInterface
{
    private readonly CacheItemPoolInterface $inner;

    public function __construct(
        public bool $throwOnGetItem = false,
        public bool $throwOnSave = false,
    ) {
        $this->inner = new ArrayAdapter();
    }

    public function getItem(string $key): CacheItemInterface
    {
        if ($this->throwOnGetItem) {
            throw new \RuntimeException('pool backend unreachable');
        }

        return $this->inner->getItem($key);
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->inner->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->inner->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->inner->deleteItem($key);
    }

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys): bool
    {
        return $this->inner->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($this->throwOnSave) {
            throw new \RuntimeException('pool backend unreachable');
        }

        return $this->inner->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->inner->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->inner->commit();
    }
}
