<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Internal;

/**
 * @internal
 */
final class Str
{
    private const int EXCERPT_LIMIT = 120;

    /**
     * Bound a string destined for an exception message: token content is
     * echoed into messages, and a multi-megabyte authored value must not
     * become a multi-megabyte log line. Byte-based on purpose (an excerpt
     * may split a multi-byte sequence; logs cope, log pipelines do not cope
     * with 5 MB lines).
     */
    public static function excerpt(string $value): string
    {
        if (\strlen($value) <= self::EXCERPT_LIMIT) {
            return $value;
        }

        return substr($value, 0, self::EXCERPT_LIMIT) . \sprintf('… (%d bytes total)', \strlen($value));
    }
}
