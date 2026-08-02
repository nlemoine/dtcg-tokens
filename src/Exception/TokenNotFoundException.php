<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Exception;

/**
 * A token path was looked up that does not exist in the collection —
 * typically a consumer-side typo or a token that was removed.
 */
class TokenNotFoundException extends TokenException
{
}
