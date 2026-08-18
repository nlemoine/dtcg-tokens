<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Number;

final readonly class CubicBezierValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * @var array{float, float, float, float}
     */
    private array $points;

    /**
     * @internal
     *
     * @param list<float> $points Exactly 4 finite numbers; x coordinates
     *                            (indexes 0 and 2) in [0, 1]
     * @param array<string, self>|null $modes
     */
    public function __construct(
        array $points,
        private ?array $modes = null,
    ) {
        if (\count($points) !== 4) {
            throw TokenException::invalidValue(\sprintf(
                'CubicBezier must be an array of 4 numbers, got %d.',
                \count($points),
            ));
        }

        foreach ($points as $index => $point) {
            if (! is_finite($point)) {
                throw TokenException::invalidValue(\sprintf('CubicBezier point %d must be a finite number.', $index));
            }
        }

        // The x coordinates (control points 0 and 2) must lie in [0, 1]; y is free.
        foreach ([0, 2] as $xIndex) {
            if ($points[$xIndex] < 0.0 || $points[$xIndex] > 1.0) {
                throw TokenException::invalidValue(\sprintf(
                    'CubicBezier x coordinate at index %d must be in [0, 1], got %s.',
                    $xIndex,
                    Number::format($points[$xIndex]),
                ));
            }
        }

        $this->points = [$points[0], $points[1], $points[2], $points[3]];
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

    public function toCss(): string
    {
        return $this->__toString();
    }

    /**
     * @return array{float, float, float, float}
     */
    public function points(): array
    {
        return $this->points;
    }
}
