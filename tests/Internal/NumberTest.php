<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Internal;

use n5s\DtcgTokens\Internal\Number;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Number::class)]
final class NumberTest extends TestCase
{
    /**
     * @return iterable<string, array{int|float, string}>
     */
    public static function provideValues(): iterable
    {
        yield 'zero' => [0.0, '0'];
        yield 'integer float' => [400.0, '400'];
        yield 'fractional' => [1.5, '1.5'];
        yield 'tiny avoids sci notation' => [0.0000001, '0.0000001'];
        yield 'huge avoids int overflow' => [1e20, '100000000000000000000'];
        yield 'negative fractional' => [-2.5, '-2.5'];
        yield 'integer type' => [42, '42'];
        // Above value*10^12 = 2^52 (value >= ~4504), naive 12-decimal formatting
        // leaks double-representation noise ("5000.000000000001").
        yield 'noise threshold integer' => [4504.0, '4504'];
        yield 'large integer float has no noise' => [5000.0, '5000'];
        yield 'negative large integer float' => [-5000.0, '-5000'];
        yield 'large fractional keeps authored precision' => [12345678.9, '12345678.9'];
        yield 'hundredths' => [0.05, '0.05'];
        yield 'precision capped at 12 decimals' => [1 / 3, '0.333333333333'];
    }

    #[DataProvider('provideValues')]
    public function testFormat(int|float $value, string $expected): void
    {
        self::assertSame($expected, Number::format($value));
    }
}
