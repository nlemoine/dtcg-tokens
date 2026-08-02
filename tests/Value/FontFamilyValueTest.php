<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\FontFamilyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontFamilyValue::class)]
final class FontFamilyValueTest extends TestCase
{
    public function testQuotesFamiliesContainingSpaces(): void
    {
        self::assertSame(
            'Inter, "Helvetica Neue"',
            (string) new FontFamilyValue(['Inter', 'Helvetica Neue']),
        );
    }

    public function testGenericFamilyKeywordsStayBare(): void
    {
        self::assertSame(
            'sans-serif, -apple-system',
            (string) new FontFamilyValue(['sans-serif', '-apple-system']),
        );
    }

    public function testEscapesDoubleQuotesInsideQuotedFamily(): void
    {
        self::assertSame(
            '"My \"Font\""',
            (string) new FontFamilyValue(['My "Font"']),
        );
    }

    public function testEscapesBackslashesInsideQuotedFamily(): void
    {
        self::assertSame(
            '"back\\\\slash"',
            (string) new FontFamilyValue(['back\\slash']),
        );
    }

    public function testFamilyStartingWithDigitIsQuoted(): void
    {
        // "9x" is not a valid CSS ident sequence unquoted.
        self::assertSame('"04b_30"', (string) new FontFamilyValue(['04b_30']));
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new FontFamilyValue(['Inter']);

        self::assertSame($value, $value->forMode('dark'));
    }
}
