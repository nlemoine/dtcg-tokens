<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Number;

final readonly class NumberValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private float $value,
        private ?array $modes = null,
    ) {
        if (! is_finite($value)) {
            throw TokenException::invalidValue('Number value must be a finite number.');
        }
    }

    public function __toString(): string
    {
        return Number::format($this->value);
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    public function value(): float
    {
        return $this->value;
    }
}
