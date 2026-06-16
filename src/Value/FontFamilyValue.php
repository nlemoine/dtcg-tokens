<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class FontFamilyValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param list<string> $families
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private array $families,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return implode(', ', array_map($this->quoteIfNeeded(...), $this->families));
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }

    private function quoteIfNeeded(string $family): string
    {
        return str_contains($family, ' ') ? '"' . $family . '"' : $family;
    }
}
