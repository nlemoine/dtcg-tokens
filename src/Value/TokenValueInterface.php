<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

interface TokenValueInterface extends \Stringable
{
    /**
     * Return the value for a specific mode, or $this if mode is unavailable.
     *
     * Mode-bound values are mode-terminal: a value returned by forMode()
     * carries no sibling map, so calling forMode() on it again (or on a
     * component accessor of a composite) returns $this. Always bind modes
     * from the base value — or use Tokens::forMode() on the collection.
     */
    public function forMode(string $mode): static;

    /**
     * The value's CSS form — identical to casting to string.
     */
    public function toCss(): string;
}
