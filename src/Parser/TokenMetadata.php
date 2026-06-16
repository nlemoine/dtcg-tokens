<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

final readonly class TokenMetadata
{
    public function __construct(
        public ?string $description = null,
        public bool $deprecated = false,
    ) {
    }
}
