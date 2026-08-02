<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Exception;

/**
 * A token source file could not be read or decoded — a deployment or
 * configuration problem, as opposed to invalid token content.
 */
class TokenFileException extends TokenException
{
}
