<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Internal\Number;

final readonly class GradientValue implements TokenValueInterface
{
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
    }

    public function __toString(): string
    {
        $parts = array_map(
            static fn (array $stop): string => \sprintf('%s %s%%', (string) $stop['color'], Number::format($stop['position'] * 100)),
            $this->stops,
        );

        return \sprintf('linear-gradient(%s)', implode(', ', $parts));
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
