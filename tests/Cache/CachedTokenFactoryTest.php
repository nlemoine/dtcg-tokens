<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Cache;

use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\ColorValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CachedTokenFactory::class)]
final class CachedTokenFactoryTest extends TestCase
{
    private const string BASE = __DIR__ . '/../fixtures/base.json';

    public function testWithoutCacheParsesFreshAndMemoizesPerInstance(): void
    {
        $factory = new CachedTokenFactory(new JsonFileLoader(self::BASE));

        $tokens = $factory->create();

        self::assertInstanceOf(Tokens::class, $tokens);
        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));

        // Second call returns the very same in-memory instance (memoized).
        self::assertSame($tokens, $factory->create());
    }

    public function testDistinctSourcesDoNotCollideInSharedPool(): void
    {
        $pool = new ArrayAdapter();

        $other = $this->writeTokenFile([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#0000ff',
                ],
            ],
        ]);

        try {
            $factoryA = new CachedTokenFactory(new JsonFileLoader(self::BASE), $pool);
            $factoryB = new CachedTokenFactory(new JsonFileLoader($other), $pool);

            // Different source sets must derive different cache keys...
            self::assertNotSame($factoryA->cacheKey(), $factoryB->cacheKey());

            // ...so neither reads the other's cached tokens from the shared pool.
            self::assertSame('rgb(255 0 0)', (string) $factoryA->create()->get('color.primary'));
            self::assertSame('rgb(0 0 255)', (string) $factoryB->create()->get('color.primary'));
        } finally {
            @unlink($other);
        }
    }

    public function testCacheMissPopulatesPoolThenHitServesWithoutReadingFiles(): void
    {
        $pool = new ArrayAdapter();
        $file = $this->writeTokenFile([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        try {
            // Miss: parse the real file and populate the shared pool.
            $factory = new CachedTokenFactory(new JsonFileLoader($file), $pool);
            self::assertSame('rgb(255 0 0)', (string) $factory->create()->get('color.primary'));
            self::assertTrue($pool->getItem($factory->cacheKey())->isHit());

            // Delete the source; a fresh factory over the SAME path (same key)
            // must serve from cache without reading the now-missing file. If it
            // called load() it would throw, so success proves the hit short-circuits.
            unlink($file);
            $served = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)->create();
            self::assertSame('rgb(255 0 0)', (string) $served->get('color.primary'));
        } finally {
            @unlink($file);
        }
    }

    public function testCacheRoundTripPreservesReadonlyValueObjects(): void
    {
        $pool = new ArrayAdapter();
        $file = $this->writeTokenFile([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        try {
            // Prime the pool, then read back purely from cache (source deleted).
            new CachedTokenFactory(new JsonFileLoader($file), $pool)->create();
            unlink($file);

            $fromCache = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)->create();

            $primary = $fromCache->get('color.primary');
            self::assertInstanceOf(ColorValue::class, $primary);
            // The readonly ColorValue still behaves after PSR-6 (serialize) round-trip.
            self::assertSame('#ff0000', $primary->toHex());
            self::assertSame('rgb(255 0 0)', $primary->toRgb());
        } finally {
            @unlink($file);
        }
    }

    public function testCacheRoundTripPreservesMetadata(): void
    {
        $file = $this->writeTokenFile([
            'color' => [
                '$type' => 'color',
                'old' => [
                    '$value' => '#ff0000',
                    '$description' => 'Legacy brand color',
                    '$deprecated' => true,
                ],
            ],
        ]);

        try {
            $pool = new ArrayAdapter();

            // Prime the pool, then read back purely from cache (source deleted).
            new CachedTokenFactory(new JsonFileLoader($file), $pool)->create();
            unlink($file);

            $fromCache = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)->create();

            $metadata = $fromCache->metadata('color.old');
            self::assertNotNull($metadata);
            self::assertSame('Legacy brand color', $metadata->description);
            self::assertTrue($metadata->deprecated);
        } finally {
            @unlink($file);
        }
    }

    public function testDebugReParsesWhenStoredMtimeIsStale(): void
    {
        $pool = new ArrayAdapter();
        $loader = new JsonFileLoader(self::BASE);
        $factory = new CachedTokenFactory($loader, $pool, debug: true);

        $this->seedStaleEntry($pool, $factory->cacheKey(), $loader->maxMtime());

        // debug=true: stale mtime must force a fresh parse from the real files.
        $tokens = $factory->create();

        self::assertTrue($tokens->has('color.primary'));
        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));
        self::assertFalse($tokens->has('color.stale'));
    }

    public function testNonDebugServesStaleEntryWithoutReParsing(): void
    {
        $pool = new ArrayAdapter();
        $loader = new JsonFileLoader(self::BASE);
        $factory = new CachedTokenFactory($loader, $pool, debug: false);

        $this->seedStaleEntry($pool, $factory->cacheKey(), $loader->maxMtime());

        // debug=false: mtime is ignored, the stale cached values are served as-is.
        $tokens = $factory->create();

        self::assertTrue($tokens->has('color.stale'));
        self::assertFalse($tokens->has('color.primary'));
    }

    /**
     * Pre-seed the pool, under the factory's own key, with an entry whose mtime
     * is deliberately older than the loader's current maxMtime and whose values
     * differ from the real fixture.
     */
    private function seedStaleEntry(CacheItemPoolInterface $pool, string $key, int $currentMtime): void
    {
        $stale = new Tokens([])->all() + [
            'color.stale' => ColorValue::fromHex('#abcdef'),
        ];

        $item = $pool->getItem($key);
        $item->set([
            'mtime' => $currentMtime - 1000,
            'values' => $stale,
            'metadata' => [],
        ]);
        $pool->save($item);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeTokenFile(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dtcg');
        self::assertIsString($path);
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
