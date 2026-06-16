<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class ShadowValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param list<array{offsetX: DimensionValue, offsetY: DimensionValue, blur: DimensionValue, spread: DimensionValue, color: ColorValue, inset: bool}> $layers
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private array $layers,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return implode(', ', array_map($this->renderLayer(...), $this->layers));
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }

    /**
     * @param array{offsetX: DimensionValue, offsetY: DimensionValue, blur: DimensionValue, spread: DimensionValue, color: ColorValue, inset: bool} $layer
     */
    private function renderLayer(array $layer): string
    {
        $css = \sprintf(
            '%s %s %s %s %s',
            (string) $layer['offsetX'],
            (string) $layer['offsetY'],
            (string) $layer['blur'],
            (string) $layer['spread'],
            (string) $layer['color'],
        );

        return $layer['inset'] ? 'inset ' . $css : $css;
    }
}
