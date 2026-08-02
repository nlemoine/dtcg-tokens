<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Internal;

/**
 * @internal
 */
final class Number
{
    /**
     * Render a number as a plain decimal string: no scientific notation,
     * no trailing zeros, no spurious decimal point. Integers render without
     * a fractional part. Precision is capped at 12 decimals.
     *
     * Formatting at a fixed 12 decimals would leak double-representation
     * noise once value*10^12 exceeds 2^52 ("5000.000000000001"), so the
     * value is rendered with the fewest decimals that reproduce it exactly.
     */
    public static function format(int|float $value): string
    {
        $float = (float) $value;

        // Fewest decimals (0..12) at which rounding reproduces the exact
        // double; fall back to the 12-decimal cap when none does.
        $decimals = 12;
        for ($candidate = 0; $candidate <= 12; $candidate++) {
            if (round($float, $candidate) === $float) {
                $decimals = $candidate;
                break;
            }
        }

        $formatted = number_format($float, $decimals, '.', '');

        if (str_contains($formatted, '.')) {
            return rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }
}
