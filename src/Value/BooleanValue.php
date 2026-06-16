<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class BooleanValue implements TokenValueInterface
{
    /**
     * @internal
     */
    public function __construct(
        private bool $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value ? 'true' : 'false';
    }

    public function value(): bool
    {
        return $this->value;
    }

    public function forMode(string $mode): static
    {
        return $this;
    }
}
