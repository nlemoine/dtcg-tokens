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

    public function testForModeReturnsSelf(): void
    {
        $value = new FontFamilyValue(['Inter']);

        self::assertSame($value, $value->forMode('dark'));
    }
}
