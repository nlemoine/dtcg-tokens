<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\CubicBezierValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CubicBezierValue::class)]
final class CubicBezierValueTest extends TestCase
{
    public function testRendersCubicBezier(): void
    {
        self::assertSame(
            'cubic-bezier(0.4, 0, 0.2, 1)',
            (string) new CubicBezierValue([0.4, 0.0, 0.2, 1.0]),
        );
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new CubicBezierValue([0.4, 0.0, 0.2, 1.0]);

        self::assertSame($value, $value->forMode('dark'));
    }
}
