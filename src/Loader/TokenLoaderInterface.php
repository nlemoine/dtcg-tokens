<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Loader;

interface TokenLoaderInterface
{
    /**
     * Load raw DTCG data (merged if multiple sources).
     *
     * @return array<string, mixed>
     */
    public function load(): array;
}
