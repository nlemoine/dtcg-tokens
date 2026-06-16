<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

use n5s\DtcgTokens\Value\TokenValueInterface;

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
