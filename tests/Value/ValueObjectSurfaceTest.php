<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Exception\TokenException;
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
use PHPUnit\Framework\TestCase;

/**
 * Cross-cutting contract of the value-object surface: toCss() on every type,
 * and component accessors on the composites.
 */
#[CoversClass(BooleanValue::class)]
#[CoversClass(BorderValue::class)]
#[CoversClass(ColorValue::class)]
#[CoversClass(CubicBezierValue::class)]
#[CoversClass(DimensionValue::class)]
#[CoversClass(FontFamilyValue::class)]
#[CoversClass(GradientValue::class)]
#[CoversClass(LinkValue::class)]
#[CoversClass(NumberValue::class)]
#[CoversClass(ShadowValue::class)]
#[CoversClass(StringValue::class)]
#[CoversClass(StrokeStyleValue::class)]
#[CoversClass(TransitionValue::class)]
#[CoversClass(TypographyValue::class)]
final class ValueObjectSurfaceTest extends TestCase
{
    /**
     * The exact CSS every token type renders. Asserting `toCss()` against
     * `(string)` would be a tautology — 13 of the 14 classes implement one
     * as a call to the other.
     *
     * @return array<string, string>
     */
    private const array EXPECTED_CSS = [
        'color' => 'rgb(255 0 0)',
        'dimension' => '1px',
        'duration' => '200ms',
        'number' => '0.5',
        'fontFamily' => 'Inter, "Helvetica Neue"',
        'fontWeight' => '700',
        'cubicBezier' => 'cubic-bezier(0.25, 0.1, 0.25, 1)',
        'boolean' => 'true',
        'string' => 'hello',
        'link' => 'https://example.com/x.svg',
        'strokeStyle' => 'dashed',
        'border' => '1px solid rgb(0 0 0)',
        'shadow' => '1px 2px 0px 0px rgb(0 0 0)',
        'gradient' => 'linear-gradient(rgb(255 0 0) 0%, rgb(0 0 255) 100%)',
        'transition' => '200ms 0ms cubic-bezier(0.25, 0.1, 0.25, 1)',
        'typography' => '400 16px Inter',
    ];

    public function testEveryTokenTypeRendersItsExpectedCss(): void
    {
        $tokens = Tokens::fromArray($this->oneTokenOfEachType());

        self::assertCount(16, $tokens);
        foreach (self::EXPECTED_CSS as $path => $expected) {
            $value = $tokens->get($path);
            self::assertSame($expected, $value->toCss(), \sprintf('toCss() of "%s"', $path));
            self::assertSame($expected, (string) $value, \sprintf('(string) of "%s"', $path));
        }
    }

    public function testBorderExposesItsComponents(): void
    {
        $border = $this->get('border');
        self::assertInstanceOf(BorderValue::class, $border);

        self::assertInstanceOf(ColorValue::class, $border->color());
        self::assertSame('#000000', $border->color()->toHex());
        self::assertSame(1.0, $border->width()->value());
        self::assertSame('px', $border->width()->unit());
        self::assertInstanceOf(StrokeStyleValue::class, $border->style());
        self::assertSame('solid', (string) $border->style());
    }

    public function testTransitionExposesItsComponents(): void
    {
        $transition = $this->get('transition');
        self::assertInstanceOf(TransitionValue::class, $transition);

        self::assertSame('200ms', (string) $transition->duration());
        self::assertSame('0ms', (string) $transition->delay());
        self::assertInstanceOf(CubicBezierValue::class, $transition->timingFunction());
    }

    public function testGradientExposesItsStops(): void
    {
        $gradient = $this->get('gradient');
        self::assertInstanceOf(GradientValue::class, $gradient);

        $stops = $gradient->stops();
        self::assertCount(2, $stops);
        self::assertSame('#ff0000', $stops[0]['color']->toHex());
        self::assertSame(0.0, $stops[0]['position']);
        self::assertSame(1.0, $stops[1]['position']);
    }

    public function testShadowExposesItsLayers(): void
    {
        $shadow = $this->get('shadow');
        self::assertInstanceOf(ShadowValue::class, $shadow);

        $layers = $shadow->layers();
        self::assertCount(1, $layers);
        self::assertSame('2px', (string) $layers[0]['offsetY']);
        self::assertFalse($layers[0]['inset']);
        self::assertInstanceOf(ColorValue::class, $layers[0]['color']);
    }

    public function testCubicBezierExposesItsPoints(): void
    {
        $bezier = $this->get('cubicBezier');
        self::assertInstanceOf(CubicBezierValue::class, $bezier);

        self::assertSame([0.25, 0.1, 0.25, 1.0], $bezier->points());
    }

    public function testFontFamilyExposesItsFamilies(): void
    {
        $fontFamily = $this->get('fontFamily');
        self::assertInstanceOf(FontFamilyValue::class, $fontFamily);

        self::assertSame(['Inter', 'Helvetica Neue'], $fontFamily->families());
    }

    private function get(string $path): \n5s\DtcgTokens\Value\TokenValueInterface
    {
        return Tokens::fromArray($this->oneTokenOfEachType())->get($path);
    }

    /**
     * One valid token of each supported $type.
     *
     * @return array<string, mixed>
     */
    private function oneTokenOfEachType(): array
    {
        $dimension = [
            'value' => 1,
            'unit' => 'px',
        ];
        $duration = [
            'value' => 200,
            'unit' => 'ms',
        ];

        return [
            'color' => [
                '$type' => 'color',
                '$value' => '#ff0000',
            ],
            'dimension' => [
                '$type' => 'dimension',
                '$value' => $dimension,
            ],
            'duration' => [
                '$type' => 'duration',
                '$value' => $duration,
            ],
            'number' => [
                '$type' => 'number',
                '$value' => 0.5,
            ],
            'fontFamily' => [
                '$type' => 'fontFamily',
                '$value' => ['Inter', 'Helvetica Neue'],
            ],
            'fontWeight' => [
                '$type' => 'fontWeight',
                '$value' => 'bold',
            ],
            'cubicBezier' => [
                '$type' => 'cubicBezier',
                '$value' => [0.25, 0.1, 0.25, 1.0],
            ],
            'boolean' => [
                '$type' => 'boolean',
                '$value' => true,
            ],
            'string' => [
                '$type' => 'string',
                '$value' => 'hello',
            ],
            'link' => [
                '$type' => 'link',
                '$value' => 'https://example.com/x.svg',
            ],
            'strokeStyle' => [
                '$type' => 'strokeStyle',
                '$value' => 'dashed',
            ],
            'border' => [
                '$type' => 'border',
                '$value' => [
                    'color' => '#000000',
                    'width' => $dimension,
                    'style' => 'solid',
                ],
            ],
            'shadow' => [
                '$type' => 'shadow',
                '$value' => [
                    'offsetX' => $dimension,
                    'offsetY' => [
                        'value' => 2,
                        'unit' => 'px',
                    ],
                    'color' => '#000000',
                ],
            ],
            'gradient' => [
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
            'transition' => [
                '$type' => 'transition',
                '$value' => [
                    'duration' => $duration,
                    'delay' => [
                        'value' => 0,
                        'unit' => 'ms',
                    ],
                    'timingFunction' => [0.25, 0.1, 0.25, 1.0],
                ],
            ],
            'typography' => [
                '$type' => 'typography',
                '$value' => [
                    'fontFamily' => 'Inter',
                    'fontSize' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                    'fontWeight' => 400,
                ],
            ],
        ];
    }

    public function testDimensionRejectsUnknownUnitAtConstruction(): void
    {
        // new DimensionValue(1.0, 'furlongs') used to be constructible: the
        // invariant lived only in the parser.
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('Invalid dimension unit "furlongs"');

        new DimensionValue(1.0, 'furlongs');
    }

    public function testDimensionRejectsNonFiniteValueAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('finite');

        new DimensionValue(\INF, 'px');
    }

    public function testNumberRejectsNonFiniteValueAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('finite');

        new NumberValue(\NAN);
    }

    public function testCubicBezierRejectsWrongPointCountAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('4 numbers');

        /* @phpstan-ignore argument.type */
        new CubicBezierValue([0.1, 0.2]);
    }

    public function testCubicBezierRejectsXOutOfRangeAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('must be in [0, 1]');

        new CubicBezierValue([5.0, 0.0, 0.5, 1.0]);
    }

    public function testFontFamilyRejectsEmptyListAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('at least one family');

        new FontFamilyValue([]);
    }

    public function testFontFamilyRejectsEmptyEntryAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('must not be empty');

        new FontFamilyValue(['Inter', '']);
    }

    public function testGradientRejectsASingleStopAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('at least two color stops');

        new GradientValue([
            [
                'color' => ColorValue::fromHex('#ff0000'),
                'position' => 0.0,
            ],
        ]);
    }

    public function testGradientRejectsOutOfRangePositionAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('must be a number in [0, 1]');

        new GradientValue([
            [
                'color' => ColorValue::fromHex('#ff0000'),
                'position' => 0.0,
            ],
            [
                'color' => ColorValue::fromHex('#0000ff'),
                'position' => 1.5,
            ],
        ]);
    }

    public function testShadowRejectsEmptyLayerListAtConstruction(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('at least one layer');

        new ShadowValue([]);
    }
}
