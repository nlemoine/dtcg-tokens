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
}
