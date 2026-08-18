<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

/**
 * Shared mode lookup: the sibling bound to $mode, the value itself when it
 * carries no modes at all, or a mode-terminal copy on a miss — the sibling
 * map must not survive a missed lookup, or a later forMode() on the result
 * would resurrect modes a projection claims it no longer has.
 *
 * @internal
 */
trait ResolvesModes
{
    public function forMode(string $mode): static
    {
        $sibling = $this->modes[$mode] ?? null;
        if ($sibling !== null) {
            return $sibling;
        }

        if ($this->modes === null) {
            return $this;
        }

        return clone $this;
    }

    public function __clone(): void
    {
        // Cloning exists solely to produce mode-terminal copies (readonly
        // properties may be reinitialized during cloning).
        $this->modes = null;
    }
}
