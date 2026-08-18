<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Internal;

use n5s\DtcgTokens\Internal\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Str::class)]
final class StrTest extends TestCase
{
    public function testShortValuePassesThrough(): void
    {
        self::assertSame('color', Str::excerpt('color'));
    }

    public function testLongValueIsTruncatedWithByteCount(): void
    {
        $excerpt = Str::excerpt(str_repeat('a', 5_000));

        self::assertLessThan(200, \strlen($excerpt));
        self::assertStringStartsWith(str_repeat('a', 120), $excerpt);
        self::assertStringContainsString('5000 bytes', $excerpt);
    }

    public function testTruncationDoesNotRequireMbstring(): void
    {
        // ext-mbstring is optional and undeclared: a consumer satisfying the
        // production requirements must not hit an undefined-function fatal.
        $source = file_get_contents(__DIR__ . '/../../src/Internal/Str.php');
        self::assertIsString($source);

        self::assertStringNotContainsString('mb_', $source);
    }

    public function testTruncationKeepsValidUtf8(): void
    {
        // A byte-level cut through a multi-byte sequence makes the message
        // unencodable: JSON log formatters drop the whole record. One ASCII
        // byte then 3-byte characters puts the 120-byte limit inside a
        // sequence, which an aligned string would never exercise.
        $excerpt = Str::excerpt('a' . str_repeat('€', 200));

        self::assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        self::assertNotFalse(json_encode($excerpt));
        self::assertLessThanOrEqual(200, \strlen($excerpt));
    }

    public function testDegenerateAllContinuationInputTruncatesToTheSuffixOnly(): void
    {
        // Invalid UTF-8 made only of continuation bytes: the walk-back must
        // stop at offset 0, not underflow into a negative substr() length.
        self::assertSame('… (200 bytes total)', Str::excerpt(str_repeat("\x80", 200)));
    }

    public function testTruncationOnAnAlignedBoundaryKeepsTheWholeCharacter(): void
    {
        // 2-byte characters divide the limit exactly: nothing to walk back.
        $excerpt = Str::excerpt(str_repeat('é', 200));

        self::assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        self::assertStringStartsWith(str_repeat('é', 60), $excerpt);
    }
}
