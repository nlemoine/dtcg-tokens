<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Number;

final readonly class GradientValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * @internal
     *
     * @param list<array{color: ColorValue, position: float}> $stops
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private array $stops,
        private ?array $modes = null,
    ) {
        // CSS needs two stops to interpolate: "linear-gradient()" and
        // "linear-gradient(red 0%)" are both invalid.
        if (\count($stops) < 2) {
            throw TokenException::invalidValue(\sprintf(
                'Gradient must contain at least two color stops, got %d.',
                \count($stops),
            ));
        }

        foreach ($stops as $stop) {
            if ($stop['position'] < 0.0 || $stop['position'] > 1.0) {
                throw TokenException::invalidValue(\sprintf(
                    'Gradient stop position must be a number in [0, 1], got %s.',
                    Number::format($stop['position']),
                ));
            }
        }
    }

    public function __toString(): string
    {
        $parts = array_map(
            static fn (array $stop): string => \sprintf('%s %s%%', (string) $stop['color'], Number::format($stop['position'] * 100)),
            $this->stops,
        );

        return \sprintf('linear-gradient(%s)', implode(', ', $parts));
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    /**
     * @return list<array{color: ColorValue, position: float}>
     */
    public function stops(): array
    {
        return $this->stops;
    }
}
