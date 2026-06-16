<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\DimensionValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DimensionValue::class)]
final class DimensionValueTest extends TestCase
{
    public function testRendersIntegerPixels(): void
    {
        self::assertSame('16px', (string) new DimensionValue(16.0, 'px'));
    }

    public function testRendersFractionalRem(): void
    {
        self::assertSame('1.5rem', (string) new DimensionValue(1.5, 'rem'));
    }

    public function testExposesValueAndUnit(): void
    {
        $value = new DimensionValue(1.5, 'rem');

        self::assertSame(1.5, $value->value());
        self::assertSame('rem', $value->unit());
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new DimensionValue(16.0, 'px');

        self::assertSame($value, $value->forMode('dark'));
    }
}
