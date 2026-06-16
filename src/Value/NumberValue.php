<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Internal\Number;

final readonly class NumberValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private float $value,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return Number::format($this->value);
    }

    public function value(): float
    {
        return $this->value;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
