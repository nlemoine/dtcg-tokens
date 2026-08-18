<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Internal;

use n5s\DtcgTokens\Exception\TokenException;
use OzdemirBurak\Iris\Color\Oklch;
use OzdemirBurak\Iris\Exceptions\InvalidColorException;

/**
 * Reduces the sRGB-reducible color spaces (srgb, hsl, oklch) to 8-bit sRGB
 * channels. This is the only place that touches the ozdemirburak/iris vendor
 * library — swapping the converter swaps the dependency.
 *
 * @internal
 */
final class SrgbConverter
{
    /**
     * Out-of-range components are clamped at this serialization boundary
     * only (storage stays verbatim), mirroring how CSS clamps at render time.
     *
     * @param array{float, float, float} $channels
     *
     * @return array{int, int, int}
     */
    public static function toRgbChannels(string $colorSpace, array $channels): array
    {
        if ($colorSpace === 'srgb') {
            return [
                (int) round(self::clamp($channels[0], 0.0, 1.0) * 255),
                (int) round(self::clamp($channels[1], 0.0, 1.0) * 255),
                (int) round(self::clamp($channels[2], 0.0, 1.0) * 255),
            ];
        }

        if ($colorSpace !== 'hsl' && $colorSpace !== 'oklch') {
            // An unguarded fallthrough would silently convert any future
            // space as oklch — wrong colors with no exception.
            throw TokenException::invalidValue(\sprintf(
                'Color space "%s" is not sRGB-reducible.',
                $colorSpace,
            ));
        }

        if ($colorSpace === 'hsl') {
            // Converted here rather than through the vendor: its HSL parser
            // only accepts integer components, which would quantize the
            // authored color (50.4% lightness becoming 50%) long before the
            // 8-bit channels are computed.
            return self::hslToRgbChannels(
                self::normalizeHue($channels[0]),
                self::clamp($channels[1], 0.0, 100.0) / 100,
                self::clamp($channels[2], 0.0, 100.0) / 100,
            );
        }

        try {
            // oklch: L is 0..1 per spec; iris expects a 0..100 percentage.
            $rgb = new Oklch(\sprintf(
                '%s,%s,%s',
                Number::format(self::clamp($channels[0], 0.0, 1.0) * 100),
                Number::format($channels[1]),
                Number::format(self::normalizeHue($channels[2])),
            ))->toRgb();
        } catch (InvalidColorException $e) {
            throw TokenException::colorConversionFailed($colorSpace, $e);
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
     * CSS Color 4 HSL-to-RGB, in floating point throughout.
     *
     * @param float $hue        wrapped into [0, 360)
     * @param float $saturation 0..1
     * @param float $lightness  0..1
     *
     * @return array{int, int, int}
     */
    private static function hslToRgbChannels(float $hue, float $saturation, float $lightness): array
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $sector = $hue / 60;
        $second = $chroma * (1 - abs(fmod($sector, 2) - 1));
        $lightest = $lightness - $chroma / 2;

        [$red, $green, $blue] = match ((int) $sector) {
            0 => [$chroma, $second, 0.0],
            1 => [$second, $chroma, 0.0],
            2 => [0.0, $chroma, $second],
            3 => [0.0, $second, $chroma],
            4 => [$second, 0.0, $chroma],
            default => [$chroma, 0.0, $second],
        };

        return [
            (int) round(($red + $lightest) * 255),
            (int) round(($green + $lightest) * 255),
            (int) round(($blue + $lightest) * 255),
        ];
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Wrap a hue angle into [0, 360) — CSS allows any angle.
     */
    private static function normalizeHue(float $hue): float
    {
        $normalized = fmod($hue, 360.0);

        return $normalized < 0.0 ? $normalized + 360.0 : $normalized;
    }
}
