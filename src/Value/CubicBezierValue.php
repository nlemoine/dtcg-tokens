<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Internal\Number;

final readonly class CubicBezierValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array{float, float, float, float} $points
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private array $points,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf(
            'cubic-bezier(%s, %s, %s, %s)',
            Number::format($this->points[0]),
            Number::format($this->points[1]),
            Number::format($this->points[2]),
            Number::format($this->points[3]),
        );
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
