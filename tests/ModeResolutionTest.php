<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests;

use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * $extensions.mode must be honored for every token type, not only color/shadow.
 */
#[CoversClass(TokenParser::class)]
#[CoversClass(Tokens::class)]
final class ModeResolutionTest extends TestCase
{
    public function testDimensionTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'space' => [
                '$type' => 'dimension',
                'gap' => [
                    '$value' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                'value' => 32,
                                'unit' => 'px',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('16px', (string) $tokens->get('space.gap'));
        self::assertSame('32px', (string) $tokens->get('space.gap', 'dark'));
    }

    public function testNumberTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'scale' => [
                '$type' => 'number',
                'factor' => [
                    '$value' => 1.5,
                    '$extensions' => [
                        'mode' => [
                            'dense' => 1.25,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('1.5', (string) $tokens->get('scale.factor'));
        self::assertSame('1.25', (string) $tokens->get('scale.factor', 'dense'));
    }

    public function testTypographyTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'type' => [
                '$type' => 'typography',
                'body' => [
                    '$value' => [
                        'fontFamily' => 'Inter',
                        'fontSize' => [
                            'value' => 16,
                            'unit' => 'px',
                        ],
                        'fontWeight' => 400,
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                'fontFamily' => 'Inter',
                                'fontSize' => [
                                    'value' => 16,
                                    'unit' => 'px',
                                ],
                                'fontWeight' => 700,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('400 16px Inter', (string) $tokens->get('type.body'));
        self::assertSame('700 16px Inter', (string) $tokens->get('type.body', 'dark'));
    }

    public function testBorderTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'border' => [
                '$type' => 'border',
                'box' => [
                    '$value' => [
                        'color' => '#000000',
                        'width' => [
                            'value' => 1,
                            'unit' => 'px',
                        ],
                        'style' => 'solid',
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                'color' => '#ffffff',
                                'width' => [
                                    'value' => 2,
                                    'unit' => 'px',
                                ],
                                'style' => 'solid',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('1px solid rgb(0 0 0)', (string) $tokens->get('border.box'));
        self::assertSame('2px solid rgb(255 255 255)', (string) $tokens->get('border.box', 'dark'));
    }

    public function testStrokeStyleTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'stroke' => [
                '$type' => 'strokeStyle',
                'divider' => [
                    '$value' => 'solid',
                    '$extensions' => [
                        'mode' => [
                            'dark' => 'dashed',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('solid', (string) $tokens->get('stroke.divider'));
        self::assertSame('dashed', (string) $tokens->get('stroke.divider', 'dark'));
    }

    public function testUnknownModeFallsBackToBaseForAnyType(): void
    {
        $tokens = Tokens::fromArray([
            'space' => [
                '$type' => 'dimension',
                'gap' => [
                    '$value' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                'value' => 32,
                                'unit' => 'px',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('16px', (string) $tokens->get('space.gap', 'does-not-exist'));
    }

    public function testAliasInsideModeResolvesToTargetSameMode(): void
    {
        // A semantic token declares its own modes, each aliasing a primitive
        // that is itself themed. Each mode must resolve to the primitive's
        // value IN THAT MODE, not its base.
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'blue' => [
                    '$value' => '#0000ff',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#000088',
                        ],
                    ],
                ],
                'bg' => [
                    '$value' => '{color.blue}',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '{color.blue}',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('rgb(0 0 255)', (string) $tokens->get('color.bg'));
        self::assertSame('rgb(0 0 136)', (string) $tokens->get('color.bg', 'dark'));
    }

    public function testModelessAliasHoistsTargetModes(): void
    {
        // `bg` aliases `fg` and declares no modes of its own; it inherits fg's
        // modes (hoisting) and resolves to fg's dark value in dark mode.
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
                'bg' => [
                    '$value' => '{color.fg}',
                ],
            ],
        ]);

        self::assertSame('rgb(255 255 255)', (string) $tokens->get('color.bg'));
        self::assertSame('rgb(0 0 0)', (string) $tokens->get('color.bg', 'dark'));
    }

    public function testTransitiveAliasHoistsModes(): void
    {
        // bg -> mid -> fg(themed): bg hoists fg's modes through the chain.
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
                'mid' => [
                    '$value' => '{color.fg}',
                ],
                'bg' => [
                    '$value' => '{color.mid}',
                ],
            ],
        ]);

        self::assertSame('rgb(255 255 255)', (string) $tokens->get('color.bg'));
        self::assertSame('rgb(0 0 0)', (string) $tokens->get('color.bg', 'dark'));
    }

    public function testCompositeHoistsModesFromNestedAlias(): void
    {
        // A border whose color aliases a themed color, with no modes of its own,
        // hoists the color's modes: in dark mode only the color channel swaps.
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'line' => [
                    '$value' => '#000000',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#ffffff',
                        ],
                    ],
                ],
            ],
            'border' => [
                '$type' => 'border',
                'divider' => [
                    '$value' => [
                        'color' => '{color.line}',
                        'width' => [
                            'value' => 1,
                            'unit' => 'px',
                        ],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        self::assertSame('1px solid rgb(0 0 0)', (string) $tokens->get('border.divider'));
        self::assertSame('1px solid rgb(255 255 255)', (string) $tokens->get('border.divider', 'dark'));
    }

    public function testDeclaredModesPreventHoistingExtraModes(): void
    {
        // `bg` declares its own `dark` while aliasing `fg`, which also has
        // `light`. Own modes win: `bg` exposes only `dark`, and `light` falls
        // back to its base value.
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'fg' => [
                    '$value' => '#ffffff',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#111111',
                            'light' => '#eeeeee',
                        ],
                    ],
                ],
                'bg' => [
                    '$value' => '{color.fg}',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#000000',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('rgb(0 0 0)', (string) $tokens->get('color.bg', 'dark'));
        // `light` is not declared on bg and not hoisted (bg has own modes):
        self::assertSame('rgb(255 255 255)', (string) $tokens->get('color.bg', 'light'));
    }

    public function testModeVariantSurvivesCacheSerialization(): void
    {
        // Value objects (with their mode siblings) must round-trip through
        // serialization, as the PSR-6 cache stores them.
        $tokens = Tokens::fromArray([
            'space' => [
                '$type' => 'dimension',
                'gap' => [
                    '$value' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                'value' => 32,
                                'unit' => 'px',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $restored = unserialize(serialize($tokens->get('space.gap')));
        self::assertSame('16px', (string) $restored);
        self::assertSame('32px', (string) $restored->forMode('dark'));
    }

    public function testNonSpecBooleanTypeIgnoresModes(): void
    {
        // boolean/string/link are non-spec extras and intentionally remain
        // non-mode-aware: a declared mode falls back to the base value.
        $tokens = Tokens::fromArray([
            'flag' => [
                '$type' => 'boolean',
                'enabled' => [
                    '$value' => true,
                    '$extensions' => [
                        'mode' => [
                            'dark' => false,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('true', (string) $tokens->get('flag.enabled'));
        self::assertSame('true', (string) $tokens->get('flag.enabled', 'dark'));
    }

    public function testDurationTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'motion' => [
                '$type' => 'duration',
                'fast' => [
                    '$value' => [
                        'value' => 100,
                        'unit' => 'ms',
                    ],
                    '$extensions' => [
                        'mode' => [
                            'slow' => [
                                'value' => 400,
                                'unit' => 'ms',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('100ms', (string) $tokens->get('motion.fast'));
        self::assertSame('400ms', (string) $tokens->get('motion.fast', 'slow'));
    }

    public function testFontFamilyTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'font' => [
                '$type' => 'fontFamily',
                'body' => [
                    '$value' => 'Inter',
                    '$extensions' => [
                        'mode' => [
                            'serif' => ['Georgia', 'serif'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('Inter', (string) $tokens->get('font.body'));
        self::assertSame('Georgia, serif', (string) $tokens->get('font.body', 'serif'));
    }

    public function testFontWeightTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'weight' => [
                '$type' => 'fontWeight',
                'label' => [
                    '$value' => 'regular',
                    '$extensions' => [
                        'mode' => [
                            'bold' => 700,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('400', (string) $tokens->get('weight.label'));
        self::assertSame('700', (string) $tokens->get('weight.label', 'bold'));
    }

    public function testCubicBezierTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'ease' => [
                '$type' => 'cubicBezier',
                'standard' => [
                    '$value' => [0.4, 0.0, 0.2, 1.0],
                    '$extensions' => [
                        'mode' => [
                            'linear' => [0.0, 0.0, 1.0, 1.0],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('cubic-bezier(0.4, 0, 0.2, 1)', (string) $tokens->get('ease.standard'));
        self::assertSame('cubic-bezier(0, 0, 1, 1)', (string) $tokens->get('ease.standard', 'linear'));
    }

    public function testGradientTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'grad' => [
                '$type' => 'gradient',
                'bg' => [
                    '$value' => [
                        [
                            'color' => '#000000',
                            'position' => 0,
                        ],
                        [
                            'color' => '#ffffff',
                            'position' => 1,
                        ],
                    ],
                    '$extensions' => [
                        'mode' => [
                            'dark' => [
                                [
                                    'color' => '#ffffff',
                                    'position' => 0,
                                ],
                                [
                                    'color' => '#000000',
                                    'position' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('linear-gradient(rgb(0 0 0) 0%, rgb(255 255 255) 100%)', (string) $tokens->get('grad.bg'));
        self::assertSame('linear-gradient(rgb(255 255 255) 0%, rgb(0 0 0) 100%)', (string) $tokens->get('grad.bg', 'dark'));
    }

    public function testTransitionTokenIsModeAware(): void
    {
        $tokens = Tokens::fromArray([
            'trans' => [
                '$type' => 'transition',
                'fade' => [
                    '$value' => [
                        'duration' => [
                            'value' => 100,
                            'unit' => 'ms',
                        ],
                        'delay' => [
                            'value' => 0,
                            'unit' => 'ms',
                        ],
                        'timingFunction' => [0.4, 0, 0.2, 1],
                    ],
                    '$extensions' => [
                        'mode' => [
                            'slow' => [
                                'duration' => [
                                    'value' => 500,
                                    'unit' => 'ms',
                                ],
                                'delay' => [
                                    'value' => 0,
                                    'unit' => 'ms',
                                ],
                                'timingFunction' => [0.4, 0, 0.2, 1],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('100ms 0ms cubic-bezier(0.4, 0, 0.2, 1)', (string) $tokens->get('trans.fade'));
        self::assertSame('500ms 0ms cubic-bezier(0.4, 0, 0.2, 1)', (string) $tokens->get('trans.fade', 'slow'));
    }
}
