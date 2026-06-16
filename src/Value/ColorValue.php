<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Number;
use OzdemirBurak\Iris\Color\Hex;
use OzdemirBurak\Iris\Color\Hsl;
use OzdemirBurak\Iris\Color\Oklch;

/**
 * A DTCG color, stored losslessly in its authored color space.
 *
 * Components are kept verbatim (a `null` component represents CSS `none` /
 * a powerless channel). Serialization to CSS Color 4 is faithful: no gamut
 * conversion is performed. Conversion to sRGB (`toHex()` / `toRgb()`) is only
 * available for sRGB-reducible spaces (`srgb`, `hsl`, `oklch`) or when an
 * author-provided `hex` fallback is present.
 */
final readonly class ColorValue implements TokenValueInterface
{
    /**
     * Spaces with a dedicated CSS function whose components are emitted verbatim.
     * `lab-d65` has no CSS function, but its values are compatible with `lab()`.
     *
     * @var array<string, string>
     */
    private const array CSS_FUNCTION_SPACES = [
        'lab' => 'lab',
        'lab-d65' => 'lab',
        'lch' => 'lch',
        'oklab' => 'oklab',
        'oklch' => 'oklch',
    ];

    /**
     * Spaces serialized via the CSS `color()` function, keyed by their CSS ident.
     *
     * @var list<string>
     */
    private const array COLOR_FUNCTION_SPACES = [
        'srgb-linear',
        'display-p3',
        'a98-rgb',
        'prophoto-rgb',
        'rec2020',
        'xyz',
        'xyz-d50',
        'xyz-d65',
    ];

    /**
     * Every accepted DTCG color space (matches terrazzo's canonical set).
     *
     * @var list<string>
     */
    private const array SUPPORTED_SPACES = [
        'srgb',
        'srgb-linear',
        'display-p3',
        'a98-rgb',
        'prophoto-rgb',
        'rec2020',
        'lab',
        'lab-d65',
        'lch',
        'oklab',
        'oklch',
        'okhsv',
        'hsl',
        'hwb',
        'xyz',
        'xyz-d50',
        'xyz-d65',
    ];

    /**
     * Spaces that can be reduced to sRGB cheaply via iris.
     */
    private const array SRGB_REDUCIBLE = ['srgb', 'hsl', 'oklch'];

    /**
     * @param list<float|null>         $components
     * @param array<string, self>|null $modes
     */
    private function __construct(
        private string $colorSpace,
        private array $components,
        private float $alpha = 1.0,
        private ?string $hex = null,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->toCss();
    }

    /**
     * Parse a 6/8-digit hex string into an sRGB color, keeping the original
     * string verbatim as the hex fallback (so `toHex()` round-trips it).
     *
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public static function fromHex(string $hex, ?array $modes = null): self
    {
        $stripped = ltrim($hex, '#');

        if (\strlen($stripped) > 6) {
            // 8-digit hex (with alpha): parse manually to avoid Iris Hexa deprecation.
            $r = hexdec(substr($stripped, 0, 2));
            $g = hexdec(substr($stripped, 2, 2));
            $b = hexdec(substr($stripped, 4, 2));
            $a = round(hexdec(substr($stripped, 6, 2)) / 255, 2);

            return new self(
                'srgb',
                [$r / 255, $g / 255, $b / 255],
                $a,
                $hex,
                $modes,
            );
        }

        // 6-digit hex: use Iris Hex::toRgb() (no deprecation).
        $color = new Hex($hex);
        $rgb = $color->toRgb();

        /** @var int $r */
        $r = $rgb->red();
        /** @var int $g */
        $g = $rgb->green();
        /** @var int $b */
        $b = $rgb->blue();

        return new self('srgb', [$r / 255, $g / 255, $b / 255], 1.0, $hex, $modes);
    }

    /**
     * The lossless path for DTCG color objects.
     *
     * @internal
     *
     * @param list<float|null>         $components
     * @param array<string, self>|null $modes
     */
    public static function fromComponents(
        string $colorSpace,
        array $components,
        float $alpha = 1.0,
        ?string $hex = null,
        ?array $modes = null,
    ): self {
        if (! \in_array($colorSpace, self::SUPPORTED_SPACES, true)) {
            throw TokenException::unsupportedColorSpace($colorSpace);
        }

        if (\count($components) < 3) {
            throw TokenException::invalidValue(\sprintf(
                'Color in space "%s" requires at least 3 components, got %d.',
                $colorSpace,
                \count($components),
            ));
        }

        return new self($colorSpace, $components, $alpha, $hex, $modes);
    }

    /**
     * Faithful CSS Color 4 serialization. No gamut conversion is performed.
     */
    public function toCss(): string
    {
        if ($this->colorSpace === 'srgb') {
            return $this->toRgb();
        }

        if ($this->colorSpace === 'hsl') {
            return \sprintf(
                'hsl(%s %s%% %s%%%s)',
                $this->component(0),
                $this->percent(1),
                $this->percent(2),
                $this->alphaSuffix(),
            );
        }

        if ($this->colorSpace === 'hwb') {
            return \sprintf(
                'hwb(%s %s%% %s%%%s)',
                $this->component(0),
                $this->percent(1),
                $this->percent(2),
                $this->alphaSuffix(),
            );
        }

        if (isset(self::CSS_FUNCTION_SPACES[$this->colorSpace])) {
            return \sprintf(
                '%s(%s%s)',
                self::CSS_FUNCTION_SPACES[$this->colorSpace],
                $this->componentList(),
                $this->alphaSuffix(),
            );
        }

        if (\in_array($this->colorSpace, self::COLOR_FUNCTION_SPACES, true)) {
            return \sprintf(
                'color(%s %s%s)',
                $this->colorSpace,
                $this->componentList(),
                $this->alphaSuffix(),
            );
        }

        // okhsv (and any space without a CSS representation): no CSS function
        // exists, so fall back to the author-provided hex if present.
        if ($this->hex !== null) {
            return strtolower($this->hex);
        }

        throw TokenException::invalidValue(\sprintf(
            'Color in space "%s" has no CSS serialization; provide a "hex" fallback.',
            $this->colorSpace,
        ));
    }

    public function toRgb(?float $alpha = null): string
    {
        $rgb = $this->toRgbChannels();
        $a = max(0.0, min(1.0, $alpha ?? $this->alpha));

        if ($a >= 1.0) {
            return \sprintf('rgb(%d %d %d)', $rgb[0], $rgb[1], $rgb[2]);
        }

        return \sprintf('rgb(%d %d %d / %s)', $rgb[0], $rgb[1], $rgb[2], $this->formatAlpha($a));
    }

    public function toHex(): string
    {
        if ($this->hex !== null) {
            return strtolower($this->hex);
        }

        [$r, $g, $b] = $this->toRgbChannels();

        if ($this->alpha < 1.0) {
            return \sprintf('#%02x%02x%02x%02x', $r, $g, $b, (int) round($this->alpha * 255));
        }

        return \sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function colorSpace(): string
    {
        return $this->colorSpace;
    }

    /**
     * @return list<float|null>
     */
    public function components(): array
    {
        return $this->components;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function hex(): ?string
    {
        return $this->hex;
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }

    /**
     * Reduce to 8-bit sRGB channels. Uses the hex fallback if present, else
     * the cheap iris path for sRGB-reducible spaces, else throws.
     *
     * @return array{int, int, int}
     */
    private function toRgbChannels(): array
    {
        // sRGB-reducible spaces compute directly from their (lossless) components.
        if (\in_array($this->colorSpace, self::SRGB_REDUCIBLE, true)) {
            return $this->reducibleToRgbChannels();
        }

        // Other spaces need an author-provided sRGB fallback.
        if ($this->hex !== null) {
            $rgb = new Hex($this->hexForIris($this->hex))->toRgb();

            /** @var int $r */
            $r = $rgb->red();
            /** @var int $g */
            $g = $rgb->green();
            /** @var int $b */
            $b = $rgb->blue();

            return [$r, $g, $b];
        }

        throw TokenException::invalidValue(\sprintf(
            'Color in space "%s" has no sRGB fallback; provide a "hex" value.',
            $this->colorSpace,
        ));
    }

    /**
     * @return array{int, int, int}
     */
    private function reducibleToRgbChannels(): array
    {
        $channels = $this->numericChannels();

        if ($this->colorSpace === 'srgb') {
            return [
                (int) round($channels[0] * 255),
                (int) round($channels[1] * 255),
                (int) round($channels[2] * 255),
            ];
        }

        if ($this->colorSpace === 'hsl') {
            $rgb = new Hsl(\sprintf('%s,%s,%s', $channels[0], $channels[1] * 100, $channels[2] * 100))->toRgb();
        } else {
            // oklch: iris expects L as a 0..100 percentage.
            $rgb = new Oklch(\sprintf('%s,%s,%s', $channels[0] * 100, $channels[1], $channels[2]))->toRgb();
        }

        /** @var int $r */
        $r = $rgb->red();
        /** @var int $g */
        $g = $rgb->green();
        /** @var int $b */
        $b = $rgb->blue();

        return [$r, $g, $b];
    }

    /**
     * The first three components as floats, treating `null` (none) as 0.
     *
     * @return array{float, float, float}
     */
    private function numericChannels(): array
    {
        return [
            (float) ($this->components[0] ?? 0.0),
            (float) ($this->components[1] ?? 0.0),
            (float) ($this->components[2] ?? 0.0),
        ];
    }

    /**
     * Iris' Hex rejects 8-digit (alpha) hex; strip the alpha pair for it.
     */
    private function hexForIris(string $hex): string
    {
        $stripped = ltrim($hex, '#');

        if (\strlen($stripped) > 6) {
            return '#' . substr($stripped, 0, 6);
        }

        return $hex;
    }

    private function component(int $index): string
    {
        $value = $this->components[$index] ?? null;

        return $value === null ? 'none' : Number::format($value);
    }

    private function percent(int $index): string
    {
        $value = $this->components[$index] ?? null;

        return $value === null ? 'none' : Number::format($value * 100);
    }

    /**
     * Space-separated rendering of the three channel components. Any stray
     * components beyond the first three are ignored so they can't corrupt the
     * function output; alpha is emitted separately via alphaSuffix().
     */
    private function componentList(): string
    {
        return implode(' ', array_map(
            static fn (float|null $c): string => $c === null ? 'none' : Number::format($c),
            \array_slice($this->components, 0, 3),
        ));
    }

    private function alphaSuffix(): string
    {
        if ($this->alpha >= 1.0) {
            return '';
        }

        return ' / ' . $this->formatAlpha(max(0.0, $this->alpha));
    }

    private function formatAlpha(float $alpha): string
    {
        $rounded = round($alpha, 2);

        // Remove trailing zeros: 0.50 -> 0.5, 1.00 -> 1
        return rtrim(rtrim(number_format($rounded, 2), '0'), '.');
    }
}
