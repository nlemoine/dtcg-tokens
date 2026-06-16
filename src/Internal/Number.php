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
     */
    public static function format(int|float $value): string
    {
        $formatted = number_format((float) $value, 12, '.', '');

        if (str_contains($formatted, '.')) {
            return rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }
}
