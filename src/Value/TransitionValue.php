<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class TransitionValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private DimensionValue $duration,
        private DimensionValue $delay,
        private CubicBezierValue $timingFunction,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf(
            '%s %s %s',
            (string) $this->duration,
            (string) $this->delay,
            (string) $this->timingFunction,
        );
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
