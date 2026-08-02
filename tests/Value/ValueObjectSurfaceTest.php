<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\BorderValue;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\CubicBezierValue;
use n5s\DtcgTokens\Value\FontFamilyValue;
use n5s\DtcgTokens\Value\GradientValue;
use n5s\DtcgTokens\Value\ShadowValue;
use n5s\DtcgTokens\Value\StrokeStyleValue;
use n5s\DtcgTokens\Value\TransitionValue;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Cross-cutting contract of the value-object surface: toCss() on every type,
 * and component accessors on the composites.
 */
#[CoversNothing]
final class ValueObjectSurfaceTest extends TestCase
{
    public function testToCssMatchesStringCastForEveryTokenType(): void
    {
        $tokens = Tokens::fromArray($this->oneTokenOfEachType());

        self::assertCount(16, $tokens);
        foreach ($tokens as $path => $value) {
            self::assertSame((string) $value, $value->toCss(), \sprintf('toCss() of "%s"', $path));
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
}
