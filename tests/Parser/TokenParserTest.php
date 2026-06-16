<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Parser;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Parser\TokenParser;
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
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenParser::class)]
#[CoversClass(\n5s\DtcgTokens\Parser\ParseResult::class)]
#[CoversClass(\n5s\DtcgTokens\Parser\TokenMetadata::class)]
final class TokenParserTest extends TestCase
{
    private TokenParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TokenParser();
    }

    public function testTypeInheritanceFromGroup(): void
    {
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        self::assertInstanceOf(ColorValue::class, $result->values['color.base']);
    }

    public function testAliasResolves(): void
    {
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#ff0000',
                ],
                'fg' => [
                    '$value' => '{color.base}',
                ],
            ],
        ]);

        self::assertSame((string) $result->values['color.base'], (string) $result->values['color.fg']);
    }

    public function testCircularAliasThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Circular alias detected');

        $this->parser->parse([
            'a' => [
                '$type' => 'color',
                '$value' => '{b}',
            ],
            'b' => [
                '$type' => 'color',
                '$value' => '{a}',
            ],
        ]);
    }

    public function testBrokenAliasThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('could not be resolved');

        $this->parser->parse([
            'a' => [
                '$type' => 'color',
                '$value' => '{does.not.exist}',
            ],
        ]);
    }

    public function testDimension(): void
    {
        $result = $this->parser->parse([
            'space' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 16,
                    'unit' => 'px',
                ],
            ],
        ]);

        $value = $result->values['space'];
        self::assertInstanceOf(DimensionValue::class, $value);
        self::assertSame('16px', (string) $value);
    }

    public function testDuration(): void
    {
        $result = $this->parser->parse([
            'fast' => [
                '$type' => 'duration',
                '$value' => [
                    'value' => 200,
                    'unit' => 'ms',
                ],
            ],
        ]);

        self::assertInstanceOf(DimensionValue::class, $result->values['fast']);
        self::assertSame('200ms', (string) $result->values['fast']);
    }

    public function testDurationSeconds(): void
    {
        $result = $this->parser->parse([
            'slow' => [
                '$type' => 'duration',
                '$value' => [
                    'value' => 1,
                    'unit' => 's',
                ],
            ],
        ]);

        self::assertInstanceOf(DimensionValue::class, $result->values['slow']);
        self::assertSame('1s', (string) $result->values['slow']);
    }

    public function testDurationInvalidUnitThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('unit');

        $this->parser->parse([
            'bad' => [
                '$type' => 'duration',
                '$value' => [
                    'value' => 5,
                    'unit' => 'px',
                ],
            ],
        ]);
    }

    public function testDimensionRem(): void
    {
        $result = $this->parser->parse([
            'space' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 1,
                    'unit' => 'rem',
                ],
            ],
        ]);

        self::assertSame('1rem', (string) $result->values['space']);
    }

    public function testDimensionInvalidUnitThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('unit');

        $this->parser->parse([
            'bad' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 100,
                    'unit' => 'vh',
                ],
            ],
        ]);
    }

    public function testDimensionLegacyBareNumberTreatedAsPixels(): void
    {
        $result = $this->parser->parse([
            'space' => [
                '$type' => 'dimension',
                '$value' => 16,
            ],
        ]);

        $value = $result->values['space'];
        self::assertInstanceOf(DimensionValue::class, $value);
        self::assertSame('16px', (string) $value);
    }

    public function testFontFamily(): void
    {
        $result = $this->parser->parse([
            'sans' => [
                '$type' => 'fontFamily',
                '$value' => ['Inter', 'Helvetica Neue'],
            ],
        ]);

        $value = $result->values['sans'];
        self::assertInstanceOf(FontFamilyValue::class, $value);
        self::assertSame('Inter, "Helvetica Neue"', (string) $value);
    }

    public function testFontWeightKeywordBold(): void
    {
        $result = $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => 'bold',
            ],
        ]);

        $value = $result->values['w'];
        self::assertInstanceOf(NumberValue::class, $value);
        self::assertSame('700', (string) $value);
    }

    public function testFontWeightKeywordBook(): void
    {
        $result = $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => 'book',
            ],
        ]);

        self::assertSame('400', (string) $result->values['w']);
    }

    public function testFontWeightNumeric(): void
    {
        $result = $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => 350,
            ],
        ]);

        self::assertSame('350', (string) $result->values['w']);
    }

    public function testFontWeightUnknownKeywordThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('unknown fontWeight keyword');

        $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => 'super-duper-bold',
            ],
        ]);
    }

    public function testFontWeightOutOfRangeThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => 1500,
            ],
        ]);
    }

    public function testNumber(): void
    {
        $result = $this->parser->parse([
            'opacity' => [
                '$type' => 'number',
                '$value' => 0.5,
            ],
        ]);

        $value = $result->values['opacity'];
        self::assertInstanceOf(NumberValue::class, $value);
        self::assertSame('0.5', (string) $value);
    }

    public function testCubicBezier(): void
    {
        $result = $this->parser->parse([
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [0.25, 0.1, 0.25, 1.0],
            ],
        ]);

        $value = $result->values['ease'];
        self::assertInstanceOf(CubicBezierValue::class, $value);
        self::assertSame('cubic-bezier(0.25, 0.1, 0.25, 1)', (string) $value);
    }

    public function testCubicBezierXOutOfRangeThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [1.5, 0, 0.2, 1],
            ],
        ]);
    }

    public function testCubicBezierAllowsYOutsideUnitRange(): void
    {
        $result = $this->parser->parse([
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [0.4, -0.5, 0.2, 1.5],
            ],
        ]);

        self::assertInstanceOf(CubicBezierValue::class, $result->values['ease']);
    }

    public function testGradientStopPositionOutOfRangeThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            'g' => [
                '$type' => 'gradient',
                '$value' => [
                    [
                        'color' => '#ff0000',
                        'position' => 1.5,
                    ],
                ],
            ],
        ]);
    }

    public function testBorder(): void
    {
        $result = $this->parser->parse([
            'b' => [
                '$type' => 'border',
                '$value' => [
                    'color' => '#000000',
                    'width' => [
                        'value' => 1,
                        'unit' => 'px',
                    ],
                    'style' => 'solid',
                ],
            ],
        ]);

        self::assertInstanceOf(BorderValue::class, $result->values['b']);
    }

    public function testTransition(): void
    {
        $result = $this->parser->parse([
            't' => [
                '$type' => 'transition',
                '$value' => [
                    'duration' => [
                        'value' => 200,
                        'unit' => 'ms',
                    ],
                    'delay' => [
                        'value' => 0,
                        'unit' => 'ms',
                    ],
                    'timingFunction' => [0.25, 0.1, 0.25, 1.0],
                ],
            ],
        ]);

        self::assertInstanceOf(TransitionValue::class, $result->values['t']);
    }

    public function testGradient(): void
    {
        $result = $this->parser->parse([
            'g' => [
                '$type' => 'gradient',
                '$value' => [
                    [
                        'color' => '#ff0000',
                        'position' => 0,
                    ],
                    [
                        'color' => '#0000ff',
                        'position' => 1,
                    ],
                ],
            ],
        ]);

        self::assertInstanceOf(GradientValue::class, $result->values['g']);
    }

    public function testTypography(): void
    {
        $result = $this->parser->parse([
            'body' => [
                '$type' => 'typography',
                '$value' => [
                    'fontFamily' => 'Inter',
                    'fontSize' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    'fontWeight' => 400,
                    'lineHeight' => 1.5,
                ],
            ],
        ]);

        self::assertInstanceOf(TypographyValue::class, $result->values['body']);
    }

    public function testTypographyPreservesUnknownSubProps(): void
    {
        $result = $this->parser->parse([
            'body' => [
                '$type' => 'typography',
                '$value' => [
                    'fontFamily' => 'Inter',
                    'fontSize' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    'fontWeight' => 400,
                    'lineHeight' => 1.5,
                    'textTransform' => 'uppercase',
                    'fontStyle' => 'italic',
                ],
            ],
        ]);

        $value = $result->values['body'];
        self::assertInstanceOf(TypographyValue::class, $value);
        self::assertSame(
            [
                'textTransform' => 'uppercase',
                'fontStyle' => 'italic',
            ],
            $value->extras(),
        );
        // Core props and shorthand are unchanged.
        self::assertSame('400 16px/1.5 Inter', (string) $value);
    }

    public function testBoolean(): void
    {
        $result = $this->parser->parse([
            'enabled' => [
                '$type' => 'boolean',
                '$value' => true,
            ],
        ]);

        $value = $result->values['enabled'];
        self::assertInstanceOf(BooleanValue::class, $value);
        self::assertSame('true', (string) $value);
    }

    public function testString(): void
    {
        $result = $this->parser->parse([
            'label' => [
                '$type' => 'string',
                '$value' => 'Hello',
            ],
        ]);

        $value = $result->values['label'];
        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame('Hello', (string) $value);
    }

    public function testLink(): void
    {
        $result = $this->parser->parse([
            'logo' => [
                '$type' => 'link',
                '$value' => 'https://example.com/logo.svg',
            ],
        ]);

        $value = $result->values['logo'];
        self::assertInstanceOf(LinkValue::class, $value);
        self::assertSame('https://example.com/logo.svg', (string) $value);
    }

    public function testStrokeStyleKeyword(): void
    {
        $result = $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => 'dashed',
            ],
        ]);

        $value = $result->values['s'];
        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertSame('dashed', (string) $value);
    }

    public function testStrokeStyleKeywordTypoThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => 'soild',
            ],
        ]);
    }

    public function testStrokeStyleObjectInvalidLineCapThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => [
                    'dashArray' => [[
                        'value' => 2,
                        'unit' => 'px',
                    ]],
                    'lineCap' => 'flat',
                ],
            ],
        ]);
    }

    public function testStrokeStyleObjectDefaultsLineCapToButt(): void
    {
        $result = $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => [
                    'dashArray' => [[
                        'value' => 2,
                        'unit' => 'px',
                    ]],
                ],
            ],
        ]);

        $value = $result->values['s'];
        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertSame('butt', $value->lineCap());
    }

    public function testStrokeStyleObject(): void
    {
        $result = $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => [
                    'dashArray' => [[
                        'value' => 2,
                        'unit' => 'px',
                    ]],
                    'lineCap' => 'round',
                ],
            ],
        ]);

        $value = $result->values['s'];
        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertSame('round', $value->lineCap());
        self::assertCount(1, $value->dashArray());
        self::assertContainsOnlyInstancesOf(DimensionValue::class, $value->dashArray());
    }

    public function testStrokeStyleObjectBareNumberDashArray(): void
    {
        $result = $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => [
                    'dashArray' => [2, 4],
                ],
            ],
        ]);

        $value = $result->values['s'];
        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertSame('butt', $value->lineCap());
        self::assertCount(2, $value->dashArray());
        self::assertSame('2px', (string) $value->dashArray()[0]);
    }

    public function testShadowWithInset(): void
    {
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 0,
                        'unit' => 'px',
                    ],
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'blur' => [
                        'value' => 4,
                        'unit' => 'px',
                    ],
                    'spread' => [
                        'value' => 0,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                    'inset' => true,
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertStringStartsWith('inset ', (string) $value);
    }

    public function testShadowWithoutInset(): void
    {
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 0,
                        'unit' => 'px',
                    ],
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'blur' => [
                        'value' => 4,
                        'unit' => 'px',
                    ],
                    'spread' => [
                        'value' => 0,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertStringStartsNotWith('inset ', (string) $value);
    }

    public function testShadowPreservesAuthoredDimensionUnit(): void
    {
        // Shadow offsets/blur/spread are DTCG dimensions; their authored unit
        // (here rem) must be emitted, not silently coerced to px.
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 0,
                        'unit' => 'rem',
                    ],
                    'offsetY' => [
                        'value' => 0.5,
                        'unit' => 'rem',
                    ],
                    'blur' => [
                        'value' => 1,
                        'unit' => 'rem',
                    ],
                    'spread' => [
                        'value' => 0,
                        'unit' => 'rem',
                    ],
                    'color' => '#000000',
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertSame('0rem 0.5rem 1rem 0rem rgb(0 0 0)', (string) $value);
    }

    public function testFontFamilyWithNonStringEntryThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('FontFamily entries must be strings');

        $this->parser->parse([
            'font' => [
                '$type' => 'fontFamily',
                'body' => [
                    '$value' => ['Inter', 123],
                ],
            ],
        ]);
    }

    public function testShadowWithNonNumericDimensionValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('must be numeric');

        $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 'nope',
                        'unit' => 'px',
                    ],
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                ],
            ],
        ]);
    }

    public function testUnsupportedTypeThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Unsupported token type "weird"');

        $this->parser->parse([
            'x' => [
                '$type' => 'weird',
                '$value' => 'nope',
            ],
        ]);
    }

    public function testMetadataDescriptionAndDeprecated(): void
    {
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'old' => [
                    '$value' => '#ff0000',
                    '$description' => 'The old red',
                    '$deprecated' => true,
                ],
            ],
        ]);

        $meta = $result->metadata['color.old'];
        self::assertSame('The old red', $meta->description);
        self::assertTrue($meta->deprecated);
    }

    public function testMetadataDefaults(): void
    {
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        $meta = $result->metadata['color.base'];
        self::assertNull($meta->description);
        self::assertFalse($meta->deprecated);
    }

    public function testGroupDeprecatedInheritsToChildren(): void
    {
        $result = $this->parser->parse([
            'legacy' => [
                '$type' => 'color',
                '$deprecated' => true,
                'a' => [
                    '$value' => '#ff0000',
                ],
                'b' => [
                    '$value' => '#00ff00',
                    '$deprecated' => false,
                ],
            ],
        ]);

        self::assertTrue($result->metadata['legacy.a']->deprecated);
        self::assertFalse($result->metadata['legacy.b']->deprecated);
    }

    public function testDeprecatedStringNormalizesToTrue(): void
    {
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'old' => [
                    '$value' => '#ff0000',
                    '$deprecated' => 'Use color.new instead',
                ],
            ],
        ]);

        self::assertTrue($result->metadata['color.old']->deprecated);
    }

    public function testPartialCompositeAlias(): void
    {
        // A border whose `color` is an alias but width/style are literal.
        // The key correctness property: per-key alias resolution inside a composite.
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                'base' => [
                    '$value' => '#112233',
                ],
            ],
            'b' => [
                '$type' => 'border',
                '$value' => [
                    'color' => '{color.base}',
                    'width' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'style' => 'solid',
                ],
            ],
        ]);

        $border = $result->values['b'];
        self::assertInstanceOf(BorderValue::class, $border);

        $expectedColor = (string) $result->values['color.base'];
        self::assertSame(\sprintf('2px solid %s', $expectedColor), (string) $border);
    }

    public function testMultiLayerShadowWithInsetOnOneLayer(): void
    {
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    [
                        'offsetX' => [
                            'value' => 0,
                            'unit' => 'px',
                        ],
                        'offsetY' => [
                            'value' => 2,
                            'unit' => 'px',
                        ],
                        'blur' => [
                            'value' => 4,
                            'unit' => 'px',
                        ],
                        'spread' => [
                            'value' => 0,
                            'unit' => 'px',
                        ],
                        'color' => '#000000',
                    ],
                    [
                        'offsetX' => [
                            'value' => 0,
                            'unit' => 'px',
                        ],
                        'offsetY' => [
                            'value' => 1,
                            'unit' => 'px',
                        ],
                        'blur' => [
                            'value' => 1,
                            'unit' => 'px',
                        ],
                        'spread' => [
                            'value' => 0,
                            'unit' => 'px',
                        ],
                        'color' => '#000000',
                        'inset' => true,
                    ],
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);

        $rendered = (string) $value;
        $parts = explode(', ', $rendered);
        self::assertCount(2, $parts);
        self::assertStringStartsNotWith('inset ', $parts[0]);
        self::assertStringStartsWith('inset ', $parts[1]);
    }

    public function testAliasToModedTokenHoistsModes(): void
    {
        // Token `bg` aliases `color.fg`, which carries a dark mode override.
        // `bg` declares no modes of its own, so it hoists fg's modes: in dark
        // mode it resolves to fg's dark value (Terrazzo-compatible behaviour).
        $result = $this->parser->parse([
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
            'bg' => [
                '$type' => 'color',
                '$value' => '{color.fg}',
            ],
        ]);

        $bg = $result->values['bg'];
        self::assertInstanceOf(ColorValue::class, $bg);

        // Base resolves to fg's base value.
        self::assertSame('rgb(255 255 255)', (string) $bg);

        // bg hoists fg's mode map: forMode('dark') resolves to fg's dark value.
        self::assertSame('rgb(0 0 0)', (string) $bg->forMode('dark'));
    }

    public function testNonScalarStringValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('scalar');

        $this->parser->parse([
            'label' => [
                '$type' => 'string',
                '$value' => ['not', 'scalar'],
            ],
        ]);
    }

    public function testNonScalarLinkValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('scalar');

        $this->parser->parse([
            'logo' => [
                '$type' => 'link',
                '$value' => ['not', 'scalar'],
            ],
        ]);
    }

    public function testNonNumericNumberValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('numeric');

        $this->parser->parse([
            'opacity' => [
                '$type' => 'number',
                '$value' => 'half',
            ],
        ]);
    }

    public function testDimensionMissingUnitThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('unit');

        $this->parser->parse([
            'space' => [
                '$type' => 'dimension',
                '$value' => [
                    'value' => 16,
                ],
            ],
        ]);
    }

    public function testTypographyMissingFontSizeThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('fontSize');

        $this->parser->parse([
            'body' => [
                '$type' => 'typography',
                '$value' => [
                    'fontFamily' => 'Inter',
                    'fontWeight' => 400,
                ],
            ],
        ]);
    }

    public function testCubicBezierWithThreeEntriesThrows(): void
    {
        $this->expectException(TokenException::class);

        $this->parser->parse([
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [0.25, 0.1, 0.25],
            ],
        ]);
    }

    public function testShadowLayerOmittingBlurAndSpreadDefaultsToZero(): void
    {
        // Per DTCG, blur and spread are OPTIONAL and default to 0.
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 1,
                        'unit' => 'px',
                    ],
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertStringContainsString('0px 0px', (string) $value);
    }

    public function testBorderRenderOutput(): void
    {
        $result = $this->parser->parse([
            'b' => [
                '$type' => 'border',
                '$value' => [
                    'color' => '#000000',
                    'width' => [
                        'value' => 1,
                        'unit' => 'px',
                    ],
                    'style' => 'solid',
                ],
            ],
        ]);

        self::assertSame('1px solid rgb(0 0 0)', (string) $result->values['b']);
    }

    public function testTransitionRenderOutput(): void
    {
        $result = $this->parser->parse([
            't' => [
                '$type' => 'transition',
                '$value' => [
                    'duration' => [
                        'value' => 200,
                        'unit' => 'ms',
                    ],
                    'delay' => [
                        'value' => 0,
                        'unit' => 'ms',
                    ],
                    'timingFunction' => [0.25, 0.1, 0.25, 1.0],
                ],
            ],
        ]);

        self::assertSame('200ms 0ms cubic-bezier(0.25, 0.1, 0.25, 1)', (string) $result->values['t']);
    }

    public function testScalarChildAlongsideTokensIsIgnored(): void
    {
        // A non-array node inside a group (e.g. a stray scalar sibling) is skipped
        // by walkTree rather than being treated as a token.
        $result = $this->parser->parse([
            'color' => [
                '$type' => 'color',
                '$schema' => 'https://example.com/schema',
                'stray' => 'not-an-object',
                'base' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        self::assertArrayHasKey('color.base', $result->values);
        self::assertArrayNotHasKey('color.stray', $result->values);
        self::assertCount(1, $result->values);
    }

    public function testDimensionWithNonNumericNonArrayValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Dimension token value expects an object, got string.');

        $this->parser->parse([
            'space' => [
                '$type' => 'dimension',
                '$value' => 'sixteen',
            ],
        ]);
    }

    public function testFontFamilyNonStringNonArrayValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('FontFamily token value must be a string or array of strings.');

        $this->parser->parse([
            'sans' => [
                '$type' => 'fontFamily',
                '$value' => 123,
            ],
        ]);
    }

    public function testFontWeightNonNumericNonStringValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('fontWeight expects a keyword or number, got bool.');

        $this->parser->parse([
            'w' => [
                '$type' => 'fontWeight',
                '$value' => true,
            ],
        ]);
    }

    public function testColorFromDtcgObjectWithChannels(): void
    {
        $result = $this->parser->parse([
            'c' => [
                '$type' => 'color',
                '$value' => [
                    'colorSpace' => 'srgb',
                    'channels' => [1.0, 0.0, 0.0],
                ],
            ],
        ]);

        $value = $result->values['c'];
        self::assertInstanceOf(ColorValue::class, $value);
        self::assertSame('srgb', $value->colorSpace());
        self::assertSame('rgb(255 0 0)', (string) $value);
    }

    public function testColorFromDtcgObjectWithComponentsAlphaNullChannelAndHexFallback(): void
    {
        $result = $this->parser->parse([
            'c' => [
                '$type' => 'color',
                '$value' => [
                    'colorSpace' => 'oklch',
                    // `components` alias for `channels`, with a null (none) channel.
                    'components' => [0.7, 0.15, null],
                    'alpha' => 0.5,
                    'hex' => '#aabbcc',
                ],
            ],
        ]);

        $value = $result->values['c'];
        self::assertInstanceOf(ColorValue::class, $value);
        self::assertSame('oklch', $value->colorSpace());
        self::assertSame([0.7, 0.15, null], $value->components());
        self::assertSame(0.5, $value->alpha());
        self::assertSame('#aabbcc', $value->hex());
    }

    public function testColorDtcgObjectMissingChannelsThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('DTCG color must have "channels" or "components".');

        $this->parser->parse([
            'c' => [
                '$type' => 'color',
                '$value' => [
                    'colorSpace' => 'srgb',
                ],
            ],
        ]);
    }

    public function testColorValueNeitherHexStringNorDtcgObjectThrows(): void
    {
        // An array without a `colorSpace` key is neither a hex string nor a
        // valid DTCG color object.
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Color token value must be a hex string or DTCG color object.');

        $this->parser->parse([
            'c' => [
                '$type' => 'color',
                '$value' => [
                    'foo' => 'bar',
                ],
            ],
        ]);
    }

    public function testTypographyLineHeightAsDimensionObject(): void
    {
        $result = $this->parser->parse([
            'body' => [
                '$type' => 'typography',
                '$value' => [
                    'fontFamily' => 'Inter',
                    'fontSize' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    'fontWeight' => 400,
                    // lineHeight as a {value, unit} dimension object rather than a bare number.
                    'lineHeight' => [
                        'value' => 1.5,
                        'unit' => 'rem',
                    ],
                ],
            ],
        ]);

        $value = $result->values['body'];
        self::assertInstanceOf(TypographyValue::class, $value);
        self::assertSame('400 16px/1.5rem Inter', (string) $value);
    }

    public function testGradientNonArrayValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Gradient token value must be an array of color stops.');

        $this->parser->parse([
            'g' => [
                '$type' => 'gradient',
                '$value' => 'not-an-array',
            ],
        ]);
    }

    public function testStrokeStyleNonStringNonArrayValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('StrokeStyle token value must be a string or object.');

        $this->parser->parse([
            's' => [
                '$type' => 'strokeStyle',
                '$value' => 42,
            ],
        ]);
    }

    public function testCubicBezierNonNumericEntryThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('CubicBezier token value entry 1 must be numeric, got string.');

        $this->parser->parse([
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [0.25, 'nope', 0.25, 1.0],
            ],
        ]);
    }

    public function testShadowNonArrayValueThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Shadow token value must be an object or array of objects.');

        $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => 'not-an-object',
            ],
        ]);
    }

    public function testShadowWithModeOverride(): void
    {
        $result = $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => [
                        'value' => 0,
                        'unit' => 'px',
                    ],
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                ],
                '$extensions' => [
                    'mode' => [
                        'dark' => [
                            'offsetX' => [
                                'value' => 0,
                                'unit' => 'px',
                            ],
                            'offsetY' => [
                                'value' => 4,
                                'unit' => 'px',
                            ],
                            'color' => '#ffffff',
                        ],
                    ],
                ],
            ],
        ]);

        $value = $result->values['sh'];
        self::assertInstanceOf(ShadowValue::class, $value);

        $dark = $value->forMode('dark');
        self::assertInstanceOf(ShadowValue::class, $dark);
        // The dark-mode override carries a distinct offset/color from the base.
        self::assertNotSame((string) $value, (string) $dark);
        self::assertStringContainsString('0px 4px', (string) $dark);
    }

    public function testMultiLayerShadowWithNonObjectLayerThrows(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Shadow layer must be an object.');

        $this->parser->parse([
            'sh' => [
                '$type' => 'shadow',
                '$value' => [
                    'not-an-object',
                ],
            ],
        ]);
    }
}
