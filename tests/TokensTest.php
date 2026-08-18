<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Export\CssExporter;
use n5s\DtcgTokens\Loader\TokenLoaderInterface;
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
        $this->expectExceptionMessageIsOrContains('Design token "does.not.exist" not found.');

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

    public function testNumericTokenPathIsUsableEndToEnd(): void
    {
        // PHP canonicalizes "4" to an int array key, so every reader of a
        // path has to cope: lookup, iteration and CSS export.
        $tokens = Tokens::fromArray([
            '4' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 4,
                    'unit' => 'px',
                ],
            ],
        ]);

        self::assertTrue($tokens->has('4'));
        self::assertSame('4px', (string) $tokens->get('4'));

        $css = new CssExporter()
            ->export($tokens);
        self::assertStringContainsString('--4: 4px;', $css);
    }

    public function testModesEnumeratesTheUnionOfDeclaredModes(): void
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
            'scale' => [
                '$type' => 'number',
                'factor' => [
                    '$value' => 1.5,
                    '$extensions' => [
                        'mode' => [
                            'dense' => 1.25,
                            'dark' => 2.0,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertEqualsCanonicalizing(['dark', 'dense'], $tokens->modes());
    }

    public function testModesIsEmptyWithoutMetadata(): void
    {
        $tokens = new Tokens([
            'a' => ColorValue::fromHex('#ff0000'),
        ]);

        self::assertSame([], $tokens->modes());
    }

    public function testForModeBindsTheWholeCollection(): void
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
                'accent' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        $dark = $tokens->forMode('dark');

        // Token with the mode switches; token without it keeps its base.
        self::assertSame('rgb(0 0 0)', (string) $dark->get('color.fg'));
        self::assertSame('rgb(255 0 0)', (string) $dark->get('color.accent'));
        // The original collection is untouched (immutability).
        self::assertSame('rgb(255 255 255)', (string) $tokens->get('color.fg'));
        // Descriptive metadata is carried over...
        self::assertNotNull($dark->metadata('color.fg'));
    }

    public function testProjectionDoesNotLeakUndeclaredModes(): void
    {
        // A token that does NOT declare the projected mode falls back to its
        // base value — but that fallback must be mode-terminal too, or a
        // second projection emits a MIX of themes across the collection.
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
                'accent' => [
                    '$value' => '#ff0000',
                    '$extensions' => [
                        'mode' => [
                            'hc' => '#00ff00',
                        ],
                    ],
                ],
            ],
        ]);

        $dark = $tokens->forMode('dark');

        // accent kept its base in the dark projection; asking the projection
        // for hc must NOT resurrect the hc sibling.
        self::assertSame('rgb(255 0 0)', (string) $dark->get('color.accent'));
        self::assertSame('rgb(255 0 0)', (string) $dark->get('color.accent', 'hc'));
        self::assertSame('rgb(255 0 0)', (string) $dark->forMode('hc')->get('color.accent'));
        // The source collection still resolves hc normally.
        self::assertSame('rgb(0 255 0)', (string) $tokens->get('color.accent', 'hc'));
    }

    public function testProjectedCollectionNoLongerAdvertisesModes(): void
    {
        // Mode-bound values carry no sibling map, so a projection cannot
        // serve any mode: it must stop claiming it can, or the natural loop
        // `foreach ($tokens->modes() as $m) ... $tokens->forMode($m)` looks
        // like it works while emitting the same theme every time.
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'fg' => [
                    '$value' => '#ffffff',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#000000',
                            'hc' => '#0000ff',
                        ],
                    ],
                ],
            ],
        ]);

        $dark = $tokens->forMode('dark');

        self::assertSame([], $dark->modes());
        self::assertSame([], $dark->metadata('color.fg')?->modes);
        // ...and a second projection cannot silently return dark values.
        self::assertSame('rgb(0 0 0)', (string) $dark->forMode('hc')->get('color.fg'));
        // The source collection still resolves every mode correctly.
        self::assertSame('rgb(0 0 255)', (string) $tokens->forMode('hc')->get('color.fg'));
    }

    public function testMetadataCarriesDeclaredModes(): void
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
                'plain' => [
                    '$value' => '#123456',
                ],
            ],
        ]);

        self::assertSame(['dark'], $tokens->metadata('color.fg')?->modes);
        self::assertSame([], $tokens->metadata('color.plain')?->modes);
    }

    public function testFromLoaderUsesTheGivenLoader(): void
    {
        // The base loader port must have a real consumer: a custom loader
        // (HTTP, database, ...) plugs into the facade without touching files.
        $loader = new class() implements TokenLoaderInterface {
            public function load(): array
            {
                return [
                    'color' => [
                        '$type' => 'color',
                        'custom' => [
                            '$value' => '#00ff00',
                        ],
                    ],
                ];
            }
        };

        $tokens = Tokens::fromLoader($loader);

        self::assertSame('rgb(0 255 0)', (string) $tokens->get('color.custom'));
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

    public function testMetadataCarriesTheDtcgType(): void
    {
        // duration/dimension share DimensionValue and fontWeight/number share
        // NumberValue: the authored $type on the metadata is how a consumer
        // tells them apart.
        $tokens = Tokens::fromArray([
            'fast' => [
                '$type' => 'duration',
                '$value' => [
                    'value' => 200,
                    'unit' => 'ms',
                ],
            ],
            'gap' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 16,
                    'unit' => 'px',
                ],
            ],
            'weight' => [
                '$type' => 'fontWeight',
                '$value' => 'bold',
            ],
        ]);

        self::assertSame('duration', $tokens->metadata('fast')?->type);
        self::assertSame('dimension', $tokens->metadata('gap')?->type);
        self::assertSame('fontWeight', $tokens->metadata('weight')?->type);
        // Projections keep it.
        self::assertSame('duration', $tokens->forMode('any')->metadata('fast')?->type);
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
