<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Exception;

/**
 * Token content is invalid: malformed values, broken or circular aliases,
 * unsupported types or color spaces — fix the token file, not the caller.
 */
class TokenParseException extends TokenException
{
}
