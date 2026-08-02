<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

final readonly class TokenMetadata
{
    /**
     * @param list<string> $modes Mode names this token resolves in (declared
     *                            or hoisted through aliases)
     */
    public function __construct(
        public ?string $description = null,
        public bool $deprecated = false,
        public array $modes = [],
    ) {
    }
}
