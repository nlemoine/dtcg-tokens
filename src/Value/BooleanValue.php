<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class BooleanValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private bool $value,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->value ? 'true' : 'false';
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    public function value(): bool
    {
        return $this->value;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
