<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\StringValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringValue::class)]
final class StringValueTest extends TestCase
{
    public function testEchoesValue(): void
    {
        $value = new StringValue('hello');

        self::assertSame('hello', $value->value());
        self::assertSame('hello', (string) $value);
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new StringValue('hello');

        self::assertSame($value, $value->forMode('dark'));
    }
}
