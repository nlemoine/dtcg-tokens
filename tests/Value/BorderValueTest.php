<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\BorderValue;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\StrokeStyleValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BorderValue::class)]
final class BorderValueTest extends TestCase
{
    public function testRendersWidthStyleColor(): void
    {
        $border = new BorderValue(
            ColorValue::fromHex('#000000'),
            new DimensionValue(1.0, 'px'),
            StrokeStyleValue::fromKeyword('solid'),
        );

        self::assertSame('1px solid rgb(0 0 0)', (string) $border);
    }

    public function testForModeReturnsSelf(): void
    {
        $border = new BorderValue(
            ColorValue::fromHex('#000000'),
            new DimensionValue(1.0, 'px'),
            StrokeStyleValue::fromKeyword('solid'),
        );

        self::assertSame($border, $border->forMode('dark'));
    }
}
