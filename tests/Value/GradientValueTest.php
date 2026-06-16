<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\GradientValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GradientValue::class)]
final class GradientValueTest extends TestCase
{
    public function testRendersLinearGradient(): void
    {
        $gradient = new GradientValue([
            [
                'color' => ColorValue::fromHex('#000000'),
                'position' => 0.0,
            ],
            [
                'color' => ColorValue::fromHex('#ffffff'),
                'position' => 1.0,
            ],
        ]);

        self::assertSame(
            'linear-gradient(rgb(0 0 0) 0%, rgb(255 255 255) 100%)',
            (string) $gradient,
        );
    }

    public function testRendersFractionalPositionWithoutTruncation(): void
    {
        $gradient = new GradientValue([
            [
                'color' => ColorValue::fromHex('#000000'),
                'position' => 0.29,
            ],
        ]);

        self::assertSame(
            'linear-gradient(rgb(0 0 0) 29%)',
            (string) $gradient,
        );
    }

    public function testRendersFractionalPercent(): void
    {
        $gradient = new GradientValue([
            [
                'color' => ColorValue::fromHex('#000000'),
                'position' => 0.335,
            ],
        ]);

        self::assertSame(
            'linear-gradient(rgb(0 0 0) 33.5%)',
            (string) $gradient,
        );
    }

    public function testForModeReturnsSelf(): void
    {
        $gradient = new GradientValue([
            [
                'color' => ColorValue::fromHex('#000000'),
                'position' => 0.0,
            ],
        ]);

        self::assertSame($gradient, $gradient->forMode('dark'));
    }
}
