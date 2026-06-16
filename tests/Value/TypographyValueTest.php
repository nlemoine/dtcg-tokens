<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\FontFamilyValue;
use n5s\DtcgTokens\Value\NumberValue;
use n5s\DtcgTokens\Value\TypographyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypographyValue::class)]
final class TypographyValueTest extends TestCase
{
    public function testRendersWeightSizeLineHeightFamily(): void
    {
        $typography = new TypographyValue(
            new FontFamilyValue(['Inter']),
            new DimensionValue(16.0, 'px'),
            new NumberValue(400.0),
            null,
            new NumberValue(1.5),
        );

        self::assertSame('400 16px/1.5 Inter', (string) $typography);
    }

    public function testAccessors(): void
    {
        $family = new FontFamilyValue(['Inter']);
        $size = new DimensionValue(16.0, 'px');
        $weight = new NumberValue(400.0);
        $letterSpacing = new DimensionValue(0.5, 'px');
        $lineHeight = new NumberValue(1.5);

        $typography = new TypographyValue($family, $size, $weight, $letterSpacing, $lineHeight);

        self::assertSame($family, $typography->fontFamily());
        self::assertSame($size, $typography->fontSize());
        self::assertSame($weight, $typography->fontWeight());
        self::assertSame($letterSpacing, $typography->letterSpacing());
        self::assertSame($lineHeight, $typography->lineHeight());
    }

    public function testExtrasDefaultEmptyAndAccessor(): void
    {
        $typography = new TypographyValue(
            new FontFamilyValue(['Inter']),
            new DimensionValue(16.0, 'px'),
            new NumberValue(400.0),
        );

        self::assertSame([], $typography->extras());

        $withExtras = new TypographyValue(
            new FontFamilyValue(['Inter']),
            new DimensionValue(16.0, 'px'),
            new NumberValue(400.0),
            null,
            null,
            [
                'textTransform' => 'uppercase',
            ],
        );

        self::assertSame([
            'textTransform' => 'uppercase',
        ], $withExtras->extras());
    }

    public function testForModeReturnsSelf(): void
    {
        $typography = new TypographyValue(
            new FontFamilyValue(['Inter']),
            new DimensionValue(16.0, 'px'),
            new NumberValue(400.0),
        );

        self::assertSame($typography, $typography->forMode('dark'));
    }
}
