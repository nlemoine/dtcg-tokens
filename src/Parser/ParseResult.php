<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

use n5s\DtcgTokens\Value\TokenValueInterface;

/**
 * Carrier for {@see TokenParser::parse()} output.
 *
 * @internal Not part of the public API: consume {@see \n5s\DtcgTokens\Tokens}
 *           instead — this shape may change without notice.
 */
final readonly class ParseResult
{
    /**
     * @param array<string, TokenValueInterface> $values
     * @param array<string, TokenMetadata> $metadata
     */
    public function __construct(
        public array $values,
        public array $metadata,
    ) {
    }
}
