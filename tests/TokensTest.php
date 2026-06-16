<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\TokenValueInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tokens::class)]
final class TokensTest extends TestCase
{
    private const string BASE = __DIR__ . '/fixtures/base.json';

    private const string OVERRIDES = __DIR__ . '/fixtures/overrides.json';

    public function testFromFileGetReturnsTypedValue(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        $primary = $tokens->get('color.primary');
        self::assertInstanceOf(ColorValue::class, $primary);
        self::assertSame('rgb(255 0 0)', (string) $primary);
    }

    public function testGetUnknownPathThrows(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Design token "does.not.exist" not found.');

        $tokens->get('does.not.exist');
    }

    public function testModeLookupDiffersFromBase(): void
    {
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'fg' => [
                    '$value' => '#ffffff',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#000000',
                        ],
                    ],
                ],
            ],
        ]);

        $base = $tokens->get('color.fg');
        $dark = $tokens->get('color.fg', 'dark');

        self::assertSame('rgb(255 255 255)', (string) $base);
        self::assertSame('rgb(0 0 0)', (string) $dark);
        self::assertNotSame((string) $base, (string) $dark);
    }

    public function testUnknownModeFallsBackToBase(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        self::assertSame('rgb(255 0 0)', (string) $tokens->get('color.primary', 'nope'));
    }

    public function testHas(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        self::assertTrue($tokens->has('color.primary'));
        self::assertFalse($tokens->has('color.nope'));
    }

    public function testCountAndAll(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        self::assertCount(2, $tokens);

        $count = $tokens->count();
        self::assertSame(2, $count);

        self::assertArrayHasKey('color.primary', $tokens->all());
        self::assertArrayHasKey('color.secondary', $tokens->all());
    }

    public function testIterationYieldsPathToValue(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        $seen = [];
        foreach ($tokens as $path => $value) {
            self::assertIsString($path);
            self::assertInstanceOf(TokenValueInterface::class, $value);
            $seen[] = $path;
        }

        self::assertSame(['color.primary', 'color.secondary'], $seen);
    }

    public function testFromFilesReflectsMerge(): void
    {
        $tokens = Tokens::fromFiles([self::BASE, self::OVERRIDES]);

        // primary overridden to blue
        self::assertSame('rgb(0 0 255)', (string) $tokens->get('color.primary'));
        // accent added by overrides, inherits group $type
        self::assertInstanceOf(ColorValue::class, $tokens->get('color.accent'));
        self::assertCount(3, $tokens);
    }

    public function testFromArray(): void
    {
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#112233',
                ],
            ],
        ]);

        self::assertTrue($tokens->has('color.base'));
        self::assertInstanceOf(ColorValue::class, $tokens->get('color.base'));
    }

    public function testEmptyCollectionCountsZeroAndIteratesEmpty(): void
    {
        $tokens = new Tokens([]);

        self::assertCount(0, $tokens);

        $seen = [];
        foreach ($tokens as $path => $value) {
            $seen[] = $path;
        }

        self::assertSame([], $seen);
    }

    public function testExposesMetadataForDescribedAndDeprecatedToken(): void
    {
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'old' => [
                    '$value' => '#112233',
                    '$description' => 'Legacy brand color',
                    '$deprecated' => true,
                ],
            ],
        ]);

        $metadata = $tokens->metadata('color.old');
        self::assertNotNull($metadata);
        self::assertSame('Legacy brand color', $metadata->description);
        self::assertTrue($metadata->deprecated);
    }

    public function testMetadataIsNullForUnknownPath(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        self::assertNull($tokens->metadata('does.not.exist'));
    }

    public function testAllMetadataIsKeyedByPath(): void
    {
        $tokens = Tokens::fromFile(self::BASE);

        self::assertArrayHasKey('color.primary', $tokens->allMetadata());
        self::assertArrayHasKey('color.secondary', $tokens->allMetadata());
    }

    public function testConstructFromValuesDirectly(): void
    {
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#112233',
                ],
            ],
        ]);

        $reconstructed = new Tokens($tokens->all());

        self::assertSame($tokens->all(), $reconstructed->all());
    }
}
