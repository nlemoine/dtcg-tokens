<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class BorderValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private ColorValue $color,
        private DimensionValue $width,
        private StrokeStyleValue $style,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('%s %s %s', (string) $this->width, (string) $this->style, (string) $this->color);
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    public function color(): ColorValue
    {
        return $this->color;
    }

    public function width(): DimensionValue
    {
        return $this->width;
    }

    public function style(): StrokeStyleValue
    {
        return $this->style;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
