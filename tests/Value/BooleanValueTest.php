<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\BooleanValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BooleanValue::class)]
final class BooleanValueTest extends TestCase
{
    public function testRendersTrue(): void
    {
        $value = new BooleanValue(true);

        self::assertTrue($value->value());
        self::assertSame('true', (string) $value);
    }

    public function testRendersFalse(): void
    {
        $value = new BooleanValue(false);

        self::assertFalse($value->value());
        self::assertSame('false', (string) $value);
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new BooleanValue(true);

        self::assertSame($value, $value->forMode('dark'));
    }
}
