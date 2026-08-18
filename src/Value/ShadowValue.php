<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;

final readonly class ShadowValue implements TokenValueInterface
{
    use ResolvesModes;

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
        if ($layers === []) {
            // Zero layers would render "--x: ;" - invalid CSS.
            throw TokenException::invalidValue('Shadow must contain at least one layer.');
        }
    }

    public function __toString(): string
    {
        return implode(', ', array_map($this->renderLayer(...), $this->layers));
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    /**
     * @return list<array{offsetX: DimensionValue, offsetY: DimensionValue, blur: DimensionValue, spread: DimensionValue, color: ColorValue, inset: bool}>
     */
    public function layers(): array
    {
        return $this->layers;
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
