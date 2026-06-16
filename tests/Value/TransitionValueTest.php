<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\CubicBezierValue;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\TransitionValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransitionValue::class)]
final class TransitionValueTest extends TestCase
{
    public function testRendersDurationDelayTimingFunction(): void
    {
        $transition = new TransitionValue(
            new DimensionValue(200.0, 'ms'),
            new DimensionValue(0.0, 'ms'),
            new CubicBezierValue([0.4, 0.0, 0.2, 1.0]),
        );

        self::assertSame('200ms 0ms cubic-bezier(0.4, 0, 0.2, 1)', (string) $transition);
    }

    public function testForModeReturnsSelf(): void
    {
        $transition = new TransitionValue(
            new DimensionValue(200.0, 'ms'),
            new DimensionValue(0.0, 'ms'),
            new CubicBezierValue([0.4, 0.0, 0.2, 1.0]),
        );

        self::assertSame($transition, $transition->forMode('dark'));
    }
}
