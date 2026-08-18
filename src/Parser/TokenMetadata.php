<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

final readonly class TokenMetadata
{
    /**
     * @param list<string> $modes Mode names this token resolves in (declared
     *                            or hoisted through aliases)
     * @param ?string      $type  The DTCG $type the token was parsed as
     *                            ("duration", "fontWeight", ...) — lets a
     *                            consumer distinguish token types that share
     *                            a value class (duration/dimension,
     *                            fontWeight/number)
     */
    public function __construct(
        public ?string $description = null,
        public bool $deprecated = false,
        public array $modes = [],
        public ?string $type = null,
    ) {
    }
}
