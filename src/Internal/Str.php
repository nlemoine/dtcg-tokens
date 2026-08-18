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
     *
     * Done without ext-mbstring, which is optional and not a requirement of
     * this package: UTF-8 continuation bytes are 10xxxxxx, so walking back
     * off them lands on the start of a sequence.
     */
    public static function excerpt(string $value): string
    {
        $length = \strlen($value);
        if ($length <= self::EXCERPT_LIMIT) {
            return $value;
        }

        $cut = self::EXCERPT_LIMIT;
        while ($cut > 0 && (\ord($value[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }

        return substr($value, 0, $cut) . \sprintf('… (%d bytes total)', $length);
    }
}
