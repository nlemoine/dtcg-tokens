<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

interface TokenValueInterface extends \Stringable
{
    /**
     * Return the value for a specific mode, or $this if mode is unavailable.
     */
    public function forMode(string $mode): static;
}
