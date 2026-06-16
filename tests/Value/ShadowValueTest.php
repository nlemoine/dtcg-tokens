<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\ShadowValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShadowValue::class)]
final class ShadowValueTest extends TestCase
{
    public function testRendersSingleLayer(): void
    {
        $shadow = new ShadowValue([
            [
                'offsetX' => new DimensionValue(0.0, 'px'),
                'offsetY' => new DimensionValue(2.0, 'px'),
                'blur' => new DimensionValue(4.0, 'px'),
                'spread' => new DimensionValue(0.0, 'px'),
                'color' => ColorValue::fromHex('#00000019'),
                'inset' => false,
            ],
        ]);

        self::assertSame('0px 2px 4px 0px rgb(0 0 0 / 0.1)', (string) $shadow);
    }

    public function testInsetLayerIsPrefixed(): void
    {
        $shadow = new ShadowValue([
            [
                'offsetX' => new DimensionValue(0.0, 'px'),
                'offsetY' => new DimensionValue(2.0, 'px'),
                'blur' => new DimensionValue(4.0, 'px'),
                'spread' => new DimensionValue(0.0, 'px'),
                'color' => ColorValue::fromHex('#000000'),
                'inset' => true,
            ],
        ]);

        self::assertStringStartsWith('inset ', (string) $shadow);
    }

    public function testFractionalPixelsArePreserved(): void
    {
        $shadow = new ShadowValue([
            [
                'offsetX' => new DimensionValue(0.5, 'px'),
                'offsetY' => new DimensionValue(0.0, 'px'),
                'blur' => new DimensionValue(0.0, 'px'),
                'spread' => new DimensionValue(0.0, 'px'),
                'color' => ColorValue::fromHex('#000000'),
                'inset' => false,
            ],
        ]);

        self::assertStringStartsWith('0.5px ', (string) $shadow);
    }

    public function testAuthoredUnitIsPreserved(): void
    {
        $shadow = new ShadowValue([
            [
                'offsetX' => new DimensionValue(0.0, 'rem'),
                'offsetY' => new DimensionValue(0.5, 'rem'),
                'blur' => new DimensionValue(1.0, 'rem'),
                'spread' => new DimensionValue(0.0, 'rem'),
                'color' => ColorValue::fromHex('#000000'),
                'inset' => false,
            ],
        ]);

        self::assertSame('0rem 0.5rem 1rem 0rem rgb(0 0 0)', (string) $shadow);
    }

    public function testForModeReturnsModeLayerWhenPresent(): void
    {
        $dark = new ShadowValue([
            [
                'offsetX' => new DimensionValue(0.0, 'px'),
                'offsetY' => new DimensionValue(4.0, 'px'),
                'blur' => new DimensionValue(8.0, 'px'),
                'spread' => new DimensionValue(0.0, 'px'),
                'color' => ColorValue::fromHex('#000000'),
                'inset' => false,
            ],
        ]);

        $light = new ShadowValue(
            [
                [
                    'offsetX' => new DimensionValue(0.0, 'px'),
                    'offsetY' => new DimensionValue(2.0, 'px'),
                    'blur' => new DimensionValue(4.0, 'px'),
                    'spread' => new DimensionValue(0.0, 'px'),
                    'color' => ColorValue::fromHex('#000000'),
                    'inset' => false,
                ],
            ],
            [
                'dark' => $dark,
            ],
        );

        self::assertSame($dark, $light->forMode('dark'));
    }
}
