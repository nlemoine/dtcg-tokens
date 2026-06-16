<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class StringValue implements TokenValueInterface
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
