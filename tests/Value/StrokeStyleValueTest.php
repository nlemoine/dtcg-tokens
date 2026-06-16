<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\StrokeStyleValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrokeStyleValue::class)]
final class StrokeStyleValueTest extends TestCase
{
    public function testFromKeyword(): void
    {
        self::assertSame('dotted', (string) StrokeStyleValue::fromKeyword('dotted'));
    }

    public function testFromObjectCapturesLineCapAndRendersDashed(): void
    {
        $style = StrokeStyleValue::fromObject([new DimensionValue(2.0, 'px')], 'round');

        self::assertSame('round', $style->lineCap());
        self::assertSame('dashed', (string) $style);
    }

    public function testEmptyDashArrayRendersSolid(): void
    {
        $style = StrokeStyleValue::fromObject([], 'butt');

        self::assertSame('solid', (string) $style);
    }

    public function testDashArrayAccessor(): void
    {
        $dimension = new DimensionValue(2.0, 'px');
        $style = StrokeStyleValue::fromObject([$dimension], 'round');

        self::assertSame([$dimension], $style->dashArray());
    }

    public function testForModeReturnsSelf(): void
    {
        $style = StrokeStyleValue::fromKeyword('solid');

        self::assertSame($style, $style->forMode('dark'));
    }

    public function testFromKeywordRejectsUnknownKeyword(): void
    {
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Invalid strokeStyle keyword "soild"; expected one of solid, dashed');

        StrokeStyleValue::fromKeyword('soild');
    }
}
