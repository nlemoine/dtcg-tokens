<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

/**
 * A token value that is semantically a URL.
 */
final readonly class LinkValue implements TokenValueInterface
{
    /**
     * @internal
     */
    public function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function forMode(string $mode): static
    {
        return $this;
    }
}
