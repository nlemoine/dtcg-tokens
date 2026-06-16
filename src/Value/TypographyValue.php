<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

final readonly class TypographyValue implements TokenValueInterface
{
    /**
     * @internal
     *
     * @param array<string, mixed> $extras
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private FontFamilyValue $fontFamilyValue,
        private DimensionValue $fontSizeValue,
        private NumberValue $fontWeightValue,
        private ?DimensionValue $letterSpacingValue = null,
        private NumberValue|DimensionValue|null $lineHeightValue = null,
        private array $extras = [],
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        $size = (string) $this->fontSizeValue;

        if ($this->lineHeightValue !== null) {
            $size .= '/' . $this->lineHeightValue;
        }

        return \sprintf(
            '%s %s %s',
            (string) $this->fontWeightValue,
            $size,
            (string) $this->fontFamilyValue,
        );
    }

    public function fontFamily(): FontFamilyValue
    {
        return $this->fontFamilyValue;
    }

    public function fontSize(): DimensionValue
    {
        return $this->fontSizeValue;
    }

    public function fontWeight(): NumberValue
    {
        return $this->fontWeightValue;
    }

    public function letterSpacing(): ?DimensionValue
    {
        return $this->letterSpacingValue;
    }

    public function lineHeight(): NumberValue|DimensionValue|null
    {
        return $this->lineHeightValue;
    }

    /**
     * Authored sub-properties beyond the five core ones (e.g. textTransform,
     * fontStyle, textDecoration), preserved verbatim.
     *
     * @return array<string, mixed>
     */
    public function extras(): array
    {
        return $this->extras;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }
}
