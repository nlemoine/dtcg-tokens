<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class StringValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private string $value,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    public function value(): string
    {
        return $this->value;
    }
}
