<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\NumberValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumberValue::class)]
final class NumberValueTest extends TestCase
{
    public function testRendersIntegerWithoutDecimal(): void
    {
        self::assertSame('400', (string) new NumberValue(400.0));
    }

    public function testRendersFractional(): void
    {
        self::assertSame('1.5', (string) new NumberValue(1.5));
    }

    public function testRendersNegativeFractional(): void
    {
        self::assertSame('-2.5', (string) new NumberValue(-2.5));
    }

    public function testTinyValueAvoidsScientificNotation(): void
    {
        self::assertSame('0.0000001', (string) new NumberValue(0.0000001));
    }

    public function testHugeValueAvoidsIntegerOverflow(): void
    {
        self::assertSame('100000000000000000000', (string) new NumberValue(1e20));
    }

    public function testExposesValue(): void
    {
        self::assertSame(1.5, new NumberValue(1.5)->value());
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new NumberValue(400.0);

        self::assertSame($value, $value->forMode('dark'));
    }
}
