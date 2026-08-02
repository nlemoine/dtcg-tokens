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
     * become a multi-megabyte log line.
     *
     * The bound is in bytes (that is what log pipelines care about) but the
     * cut lands on a codepoint boundary: a split multi-byte sequence makes
     * the message unencodable, and JSON log formatters drop the record.
     */
    public static function excerpt(string $value): string
    {
        if (\strlen($value) <= self::EXCERPT_LIMIT) {
            return $value;
        }

        return mb_strcut($value, 0, self::EXCERPT_LIMIT, 'UTF-8') . \sprintf('… (%d bytes total)', \strlen($value));
    }
}
