<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Cache;

use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\CacheableTokenLoaderInterface;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Parser\TokenMetadata;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\BooleanValue;
use n5s\DtcgTokens\Value\BorderValue;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\CubicBezierValue;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\FontFamilyValue;
use n5s\DtcgTokens\Value\GradientValue;
use n5s\DtcgTokens\Value\LinkValue;
use n5s\DtcgTokens\Value\NumberValue;
use n5s\DtcgTokens\Value\ShadowValue;
use n5s\DtcgTokens\Value\StringValue;
use n5s\DtcgTokens\Value\StrokeStyleValue;
use n5s\DtcgTokens\Value\TransitionValue;
use n5s\DtcgTokens\Value\TypographyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testCacheKeyCarriesAFormatVersion(): void
    {
        // The cached payload's shape is the value objects' private property
        // layout. A version segment in the key invalidates pools that survive
        // deploys (Redis, APCu) when that layout changes between releases.
        $factory = new CachedTokenFactory(new JsonFileLoader(self::BASE));

        // v3: TokenMetadata gained `modes` and `type` (serialized shape change).
        self::assertStringStartsWith('n5s_dtcg_tokens.v3.', $factory->cacheKey());
    }

    public function testCacheVersionIsPinnedToTheSerializedShapes(): void
    {
        // The pool stores serialize()d value-object graphs: renaming a single
        // private property is an internal change that becomes a production
        // incident on any deploy sharing a persistent pool — unless
        // CACHE_VERSION is bumped. This pin forces that discipline.
        $classes = [
            TokenMetadata::class,
            BooleanValue::class,
            BorderValue::class,
            ColorValue::class,
            CubicBezierValue::class,
            DimensionValue::class,
            FontFamilyValue::class,
            GradientValue::class,
            LinkValue::class,
            NumberValue::class,
            ShadowValue::class,
            StringValue::class,
            StrokeStyleValue::class,
            TransitionValue::class,
            TypographyValue::class,
        ];

        $shapes = [];
        foreach ($classes as $class) {
            $properties = array_map(
                static fn (\ReflectionProperty $property): string => $property->getName() . ':' . $property->getType(),
                new \ReflectionClass($class)
                    ->getProperties(),
            );
            sort($properties);
            $shapes[$class] = $properties;
        }

        self::assertSame(
            'd2d740156ea4d73a959e50adb89604d0',
            hash('xxh128', (string) json_encode($shapes)),
            'The serialized shape of cached value objects changed: bump CachedTokenFactory::CACHE_VERSION, then update this pinned hash.',
        );
    }

    public function testTtlIsAppliedToCacheWrites(): void
    {
        $pool = new ArrayAdapter();

        // A negative TTL writes an already-expired entry: if expiresAfter()
        // is honored, the very next factory must miss and re-parse.
        new CachedTokenFactory($this->countingLoader(), $pool, ttl: -1)
            ->create();

        $loader = $this->countingLoader();
        new CachedTokenFactory($loader, $pool)
            ->create();

        self::assertSame(1, $loader->loadCalls);
    }

    public function testCustomCacheableLoaderIsAccepted(): void
    {
        // The factory must accept any cacheable loader, not just JsonFileLoader:
        // a consumer's HTTP/database loader keeps caching support.
        $loader = new class() implements CacheableTokenLoaderInterface {
            public function load(): array
            {
                return [
                    'color' => [
                        '$type' => 'color',
                        'primary' => [
                            '$value' => '#00ff00',
                        ],
                    ],
                ];
            }

            public function revision(): ?string
            {
                return null;
            }

            public function fingerprint(): string
            {
                return 'custom-loader-fingerprint';
            }
        };

        $pool = new ArrayAdapter();
        $factory = new CachedTokenFactory($loader, $pool);

        self::assertSame('rgb(0 255 0)', (string) $factory->create()->get('color.primary'));
        self::assertTrue($pool->getItem($factory->cacheKey())->isHit());
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
            $served = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)
                ->create();
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
            new CachedTokenFactory(new JsonFileLoader($file), $pool)
                ->create();
            unlink($file);

            $fromCache = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)
                ->create();

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
            new CachedTokenFactory(new JsonFileLoader($file), $pool)
                ->create();
            unlink($file);

            $fromCache = new CachedTokenFactory(new JsonFileLoader($file), $pool, debug: false)
                ->create();

            $metadata = $fromCache->metadata('color.old');
            self::assertNotNull($metadata);
            self::assertSame('Legacy brand color', $metadata->description);
            self::assertTrue($metadata->deprecated);
        } finally {
            @unlink($file);
        }
    }

    public function testNonDebugWarmPoolHitNeverTouchesSources(): void
    {
        $pool = new ArrayAdapter();
        new CachedTokenFactory($this->countingLoader(), $pool)
            ->create();

        // Fresh factory (empty in-process memo) over a warm pool: production
        // mode must serve the hit without a single stat or read.
        $loader = $this->countingLoader();
        $tokens = new CachedTokenFactory($loader, $pool, debug: false)
            ->create();

        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));
        self::assertSame(0, $loader->loadCalls);
        self::assertSame(0, $loader->revisionCalls);
    }

    public function testDebugMemoRevalidatesWhenSourcesChange(): void
    {
        // Long-running workers (FrankenPHP, RoadRunner): in debug, the
        // in-process memo must not mask a source edit on later create() calls.
        $loader = $this->countingLoader();
        $factory = new CachedTokenFactory($loader, debug: true);

        self::assertTrue($factory->create()->has('color.primary'));

        $loader->raw = [
            'color' => [
                '$type' => 'color',
                'two' => [
                    '$value' => '#00ff00',
                ],
            ],
        ];
        $loader->revision = '2000';

        $second = $factory->create();
        self::assertTrue($second->has('color.two'));
        self::assertFalse($second->has('color.primary'));
    }

    public function testDebugMemoIsReusedWhileSourcesUnchanged(): void
    {
        $loader = $this->countingLoader();
        $factory = new CachedTokenFactory($loader, debug: true);

        $first = $factory->create();

        self::assertSame($first, $factory->create());
        self::assertSame(1, $loader->loadCalls);
    }

    public function testDebugServesFreshPoolEntryWithoutReparsing(): void
    {
        // The fourth quadrant: debug=true AND stored revision matches — the
        // cached entry is fresh and must be served without a re-parse.
        $pool = new ArrayAdapter();
        $loader = $this->countingLoader();
        $factory = new CachedTokenFactory($loader, $pool, debug: true);

        $this->seedEntry($pool, $factory->cacheKey(), $loader->revision);

        $tokens = $factory->create();

        self::assertTrue($tokens->has('color.stale'));
        self::assertSame(0, $loader->loadCalls);
    }

    public function testDebugReParsesWhenStoredRevisionIsStale(): void
    {
        $pool = new ArrayAdapter();
        $loader = new JsonFileLoader(self::BASE);
        $factory = new CachedTokenFactory($loader, $pool, debug: true);

        $this->seedStaleEntry($pool, $factory->cacheKey());

        // debug=true: a stale revision must force a fresh parse from the real files.
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

        $this->seedStaleEntry($pool, $factory->cacheKey());

        // debug=false: the revision is ignored, the stale cached values are served as-is.
        $tokens = $factory->create();

        self::assertTrue($tokens->has('color.stale'));
        self::assertFalse($tokens->has('color.primary'));
    }

    public function testThrowingPoolFallsBackToFreshParse(): void
    {
        // The cache is an optimization, not a dependency: a pool whose
        // backend is down must not take token resolution down with it.
        $pool = new FlakyPool(throwOnGetItem: true);
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        $tokens = $factory->create();

        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));
        // A completely unreachable pool must be distinguishable from "no
        // pool configured" — that distinction is cacheWritten()'s one job.
        self::assertFalse($factory->cacheWritten());
    }

    public function testSaveFailureDoesNotDiscardParsedTokens(): void
    {
        // The parse already succeeded when save() runs; a write failure only
        // costs the next request a re-parse.
        $pool = new FlakyPool(throwOnSave: true);
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        $tokens = $factory->create();

        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));
    }

    public function testNumericTokenPathRoundTripsThroughTheCache(): void
    {
        // A numeric path arrives as an int array key: the payload validator
        // must not reject the entry the factory just wrote, or every request
        // re-parses and re-writes forever.
        $pool = new ArrayAdapter();
        $loader = new CountingLoader([
            '4' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 4,
                    'unit' => 'px',
                ],
            ],
        ]);

        new CachedTokenFactory($loader, $pool)
            ->create();

        $second = new CountingLoader($loader->raw);
        $tokens = new CachedTokenFactory($second, $pool)
            ->create();

        self::assertSame('4px', (string) $tokens->get('4'));
        self::assertSame(0, $second->loadCalls, 'the cached entry must be served, not re-parsed');
    }

    public function testFingerprintWithPsr6ReservedCharactersStillCaches(): void
    {
        // A custom loader may fingerprint with a URL or a DSN; PSR-6 reserves
        // {}()/\@: in keys, so an unhashed fingerprint would make every pool
        // call throw and silently disable caching for good.
        $pool = new ArrayAdapter();
        $loader = new CountingLoader([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ], fingerprint: 'https://cdn.example.com/tokens.json?v=1{a}');

        $factory = new CachedTokenFactory($loader, $pool);

        self::assertSame('rgb(255 0 0)', (string) $factory->create()->get('color.primary'));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/', $factory->cacheKey());
        self::assertTrue($pool->getItem($factory->cacheKey())->isHit());
        self::assertTrue($factory->cacheWritten());
    }

    public function testFailedWriteIsReportedRatherThanAssumedSuccessful(): void
    {
        // PSR-6 save() reports failure by returning false, not by throwing.
        $pool = new FlakyPool(failSave: true);
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        self::assertSame('rgb(255 0 0)', (string) $factory->create()->get('color.primary'));
        self::assertFalse($factory->cacheWritten());
    }

    public function testSuccessfulWriteIsReported(): void
    {
        $factory = new CachedTokenFactory($this->countingLoader(), new ArrayAdapter());
        $factory->create();

        self::assertTrue($factory->cacheWritten());
    }

    public function testNoWriteAttemptIsDistinguishableFromAFailedOne(): void
    {
        $factory = new CachedTokenFactory($this->countingLoader());
        $factory->create();

        self::assertNull($factory->cacheWritten());
    }

    public function testPoisonedEntryIsOverwrittenRatherThanRetriedForever(): void
    {
        // An entry whose payload cannot be unserialized must be replaced, not
        // re-read (and re-rejected) on every request.
        $pool = new ArrayAdapter();
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        $item = $pool->getItem($factory->cacheKey());
        $item->set([
            'revision' => '1',
            'values' => 'poison',
            'metadata' => [],
        ]);
        $pool->save($item);

        $factory->create();

        self::assertTrue($factory->cacheWritten());
        $fresh = new CountingLoader($this->countingLoader()->raw);
        new CachedTokenFactory($fresh, $pool)
            ->create();
        self::assertSame(0, $fresh->loadCalls);
    }

    public function testUnreadablePayloadIsTreatedAsMissAndOverwritten(): void
    {
        // The backend reports a hit but the payload cannot be unserialized
        // (truncated write, class gone). That must degrade to a fresh parse
        // AND replace the entry, or every request pays for it forever.
        $pool = new FlakyPool(unreadablePayload: true);
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        self::assertSame('rgb(255 0 0)', (string) $factory->create()->get('color.primary'));
        self::assertTrue($factory->cacheWritten());
        self::assertIsArray($pool->lastUnreadableItem?->written);
    }

    public function testCorruptedPayloadIsTreatedAsMissAndOverwritten(): void
    {
        // A truncated write, a foreign entry under our key, or an
        // __PHP_Incomplete_Class must behave as a miss, not a TypeError.
        $pool = new ArrayAdapter();
        $factory = new CachedTokenFactory($this->countingLoader(), $pool);

        $item = $pool->getItem($factory->cacheKey());
        $item->set('garbage, not our payload shape');
        $pool->save($item);

        $tokens = $factory->create();

        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary'));
        // Self-healing: the corrupted entry was replaced by a valid one.
        $fresh = new CachedTokenFactory($this->countingLoader(), $pool, debug: false)
            ->create();
        self::assertSame('rgb(255 0 0)', (string) $fresh->get('color.primary'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'not an array' => ['garbage'];
        yield 'missing revision' => [[
            'values' => [],
            'metadata' => [],
        ]];
        yield 'non-string revision' => [[
            'revision' => 20_260_818,
            'values' => [],
            'metadata' => [],
        ]];
        yield 'missing values' => [[
            'revision' => '1',
            'metadata' => [],
        ]];
        yield 'non-array values' => [[
            'revision' => '1',
            'values' => 'nope',
            'metadata' => [],
        ]];
        yield 'missing metadata' => [[
            'revision' => '1',
            'values' => [],
        ]];
        yield 'non-array metadata' => [[
            'revision' => '1',
            'values' => [],
            'metadata' => 'nope',
        ]];
        yield 'value entry is not a token value' => [[
            'revision' => '1',
            'values' => [
                'a' => 'not-a-value-object',
            ],
            'metadata' => [],
        ]];
        yield 'metadata entry is not TokenMetadata' => [[
            'revision' => '1',
            'values' => [],
            'metadata' => [
                'a' => 'not-metadata',
            ],
        ]];
    }

    #[DataProvider('provideInvalidPayloads')]
    public function testEveryInvalidPayloadShapeIsTreatedAsMiss(mixed $payload): void
    {
        $pool = new ArrayAdapter();
        $factory = new CachedTokenFactory($this->countingLoader(), $pool, debug: false);

        $item = $pool->getItem($factory->cacheKey());
        $item->set($payload);
        $pool->save($item);

        // Any shape defect must fall through to a fresh parse, never a hit.
        self::assertSame('rgb(255 0 0)', (string) $factory->create()->get('color.primary'));
    }

    public function testDebugTreatsUnknownRevisionAsAlwaysStale(): void
    {
        // A loader honestly reporting "revision unknown" (null, per the
        // interface contract) must not freeze the cache: null === null is
        // not evidence of freshness.
        $pool = new ArrayAdapter();
        $loader = new CountingLoader([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ], revision: null);
        $factory = new CachedTokenFactory($loader, $pool, debug: true);

        $this->seedEntry($pool, $factory->cacheKey(), null);

        $tokens = $factory->create();

        self::assertTrue($tokens->has('color.primary'));
        self::assertFalse($tokens->has('color.stale'));
    }

    public function testDebugMemoIsNotReusedWhenRevisionIsUnknown(): void
    {
        $loader = new CountingLoader([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ], revision: null);
        $factory = new CachedTokenFactory($loader, debug: true);

        self::assertTrue($factory->create()->has('color.primary'));

        $loader->raw = [
            'color' => [
                '$type' => 'color',
                'two' => [
                    '$value' => '#00ff00',
                ],
            ],
        ];

        // the revision stays unknown: debug must re-parse rather than trust it.
        self::assertTrue($factory->create()->has('color.two'));
    }

    private function countingLoader(): CountingLoader
    {
        return new CountingLoader([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);
    }

    /**
     * Pre-seed the pool, under the factory's own key, with an entry whose
     * revision differs from the loader's current one and whose values differ
     * from the real fixture.
     */
    private function seedStaleEntry(CacheItemPoolInterface $pool, string $key): void
    {
        $this->seedEntry($pool, $key, 'stale-revision');
    }

    /**
     * Pre-seed the pool with a recognizable entry ("color.stale") at an
     * arbitrary stored revision.
     */
    private function seedEntry(CacheItemPoolInterface $pool, string $key, ?string $revision): void
    {
        $item = $pool->getItem($key);
        $item->set([
            'revision' => $revision,
            'values' => [
                'color.stale' => ColorValue::fromHex('#abcdef'),
            ],
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
