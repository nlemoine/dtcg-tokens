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

    public function toCss(): string
    {
        return $this->__toString();
    }

    /**
     * The transition duration. Durations are carried by {@see DimensionValue}
     * with an `ms`/`s` unit — see the note on that class.
     */
    public function duration(): DimensionValue
    {
        return $this->duration;
    }

    public function delay(): DimensionValue
    {
        return $this->delay;
    }

    public function timingFunction(): CubicBezierValue
    {
        return $this->timingFunction;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
