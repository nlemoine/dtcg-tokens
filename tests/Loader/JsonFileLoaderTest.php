<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Loader;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Loader\TokenLoaderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFileLoader::class)]
final class JsonFileLoaderTest extends TestCase
{
    private const string BASE = __DIR__ . '/../fixtures/base.json';

    private const string OVERRIDES = __DIR__ . '/../fixtures/overrides.json';

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TokenLoaderInterface::class, new JsonFileLoader(self::BASE));
    }

    public function testLoadsSingleFile(): void
    {
        $loader = new JsonFileLoader(self::BASE);
        $raw = $loader->load();

        self::assertArrayHasKey('color', $raw);
        self::assertSame('color', $raw['color']['$type']);
        self::assertSame('#ff0000', $raw['color']['primary']['$value']);
    }

    public function testMergesTwoFilesLaterWins(): void
    {
        $loader = new JsonFileLoader(self::BASE, self::OVERRIDES);
        $raw = $loader->load();

        // overrides changes primary
        self::assertSame('#0000ff', $raw['color']['primary']['$value']);
        // base value untouched where not overridden
        self::assertSame('#00ff00', $raw['color']['secondary']['$value']);
        // overrides adds accent
        self::assertSame('#ffff00', $raw['color']['accent']['$value']);
        // group $type from base is preserved through the merge
        self::assertSame('color', $raw['color']['$type']);
    }

    public function testFromPathsBehavesIdentically(): void
    {
        $variadic = new JsonFileLoader(self::BASE, self::OVERRIDES)->load();
        $named = JsonFileLoader::fromPaths([self::BASE, self::OVERRIDES])->load();

        self::assertSame($variadic, $named);
    }

    public function testThrowsOnMissingFile(): void
    {
        $loader = new JsonFileLoader(__DIR__ . '/../fixtures/does-not-exist.json');

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('does-not-exist.json');

        @$loader->load();
    }

    public function testThrowsOnNonArrayTopLevel(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dtcg');
        self::assertIsString($tmp);
        file_put_contents($tmp, '"just a string"');

        try {
            $loader = new JsonFileLoader($tmp);

            $this->expectException(TokenException::class);
            $this->expectExceptionMessage('object');

            $loader->load();
        } finally {
            unlink($tmp);
        }
    }

    public function testThrowsOnMalformedJson(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dtcg');
        self::assertIsString($tmp);
        file_put_contents($tmp, '{ "color": '); // truncated, invalid JSON

        try {
            $loader = new JsonFileLoader($tmp);

            try {
                $loader->load();
                self::fail('Expected TokenException for malformed JSON.');
            } catch (TokenException $e) {
                self::assertStringContainsString('invalid JSON', $e->getMessage());
                self::assertInstanceOf(\JsonException::class, $e->getPrevious());
            }
        } finally {
            unlink($tmp);
        }
    }

    public function testMaxMtimeIsPositive(): void
    {
        $loader = new JsonFileLoader(self::BASE, self::OVERRIDES);

        self::assertGreaterThan(0, $loader->maxMtime());
    }

    public function testMaxMtimeWithNoFilesIsZero(): void
    {
        $loader = new JsonFileLoader();

        self::assertSame(0, $loader->maxMtime());
    }

    public function testMaxMtimeSkipsMissingFile(): void
    {
        $missing = __DIR__ . '/../fixtures/does-not-exist.json';
        $loader = new JsonFileLoader(self::BASE, $missing);

        $expected = filemtime(self::BASE);
        self::assertIsInt($expected);
        // The missing path is skipped (not fatal); the existing file's mtime wins.
        self::assertSame($expected, @$loader->maxMtime());
    }

    public function testLaterFileReplacesListValueWholesale(): void
    {
        $base = $this->writeTempJson([
            'font' => [
                '$type' => 'fontFamily',
                'body' => [
                    '$value' => ['Inter', 'Arial', 'sans-serif'],
                ],
            ],
        ]);
        $override = $this->writeTempJson([
            'font' => [
                '$type' => 'fontFamily',
                'body' => [
                    '$value' => ['Roboto'],
                ],
            ],
        ]);

        try {
            $raw = new JsonFileLoader($base, $override)->load();

            // The override list replaces wholesale: no tail elements leak through.
            self::assertSame(['Roboto'], $raw['font']['body']['$value']);
        } finally {
            unlink($base);
            unlink($override);
        }
    }

    public function testLaterFileReplacesTokenValueWholesale(): void
    {
        // A later $value replaces the earlier one entirely, so stale sub-keys
        // (here a hex fallback) cannot leak into the redefined value.
        $base = $this->writeTempJson([
            'color' => [
                '$type' => 'color',
                'brand' => [
                    '$value' => [
                        'colorSpace' => 'srgb',
                        'components' => [1, 0, 0],
                        'hex' => '#ff0000',
                    ],
                ],
            ],
        ]);
        $override = $this->writeTempJson([
            'color' => [
                '$type' => 'color',
                'brand' => [
                    '$value' => [
                        'colorSpace' => 'display-p3',
                        'components' => [0, 1, 0],
                    ],
                ],
            ],
        ]);

        try {
            $raw = new JsonFileLoader($base, $override)->load();

            self::assertSame([
                'colorSpace' => 'display-p3',
                'components' => [0, 1, 0],
            ], $raw['color']['brand']['$value']);
        } finally {
            unlink($base);
            unlink($override);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeTempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dtcg');
        self::assertIsString($path);
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
