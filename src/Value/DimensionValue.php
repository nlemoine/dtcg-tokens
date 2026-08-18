<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Number;
use n5s\DtcgTokens\Internal\Str;

/**
 * A value with a CSS unit. Carries both DTCG `dimension` tokens (px/rem/em)
 * and `duration` tokens (ms/s): their serialization and value/unit surface
 * are identical, so they share one class — the parser's unit whitelist is
 * what tells the two token types apart.
 */
final readonly class DimensionValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * Every unit either token type can carry (dimension: px/rem/em,
     * duration: ms/s). The parser narrows further per token type.
     *
     * @var list<string>
     */
    private const array UNITS = ['px', 'rem', 'em', 'ms', 's'];

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
        if (! \in_array($unit, self::UNITS, true)) {
            throw TokenException::invalidValue(\sprintf(
                'Invalid dimension unit "%s"; expected one of %s.',
                Str::excerpt($unit),
                implode(', ', self::UNITS),
            ));
        }

        if (! is_finite($value)) {
            throw TokenException::invalidValue('Dimension value must be a finite number.');
        }
    }

    public function __toString(): string
    {
        // Render integers without decimal point: 9999px not 9999.0px
        return Number::format($this->value) . $this->unit;
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    public function value(): float
    {
        return $this->value;
    }

    public function unit(): string
    {
        return $this->unit;
    }
}
