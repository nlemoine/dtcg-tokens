<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Value\LinkValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkValue::class)]
final class LinkValueTest extends TestCase
{
    public function testEchoesValue(): void
    {
        $value = new LinkValue('https://example.com/logo.svg');

        self::assertSame('https://example.com/logo.svg', $value->value());
        self::assertSame('https://example.com/logo.svg', (string) $value);
    }

    public function testForModeReturnsSelf(): void
    {
        $value = new LinkValue('https://example.com/logo.svg');

        self::assertSame($value, $value->forMode('dark'));
    }
}
