<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Internal\Number;

final readonly class DimensionValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private float $value,
        private string $unit,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        // Render integers without decimal point: 9999px not 9999.0px
        return Number::format($this->value) . $this->unit;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
