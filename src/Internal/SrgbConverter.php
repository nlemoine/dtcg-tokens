<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Internal;

use n5s\DtcgTokens\Exception\TokenException;
use OzdemirBurak\Iris\Color\Hsl;
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

        try {
            if ($colorSpace === 'hsl') {
                // DTCG spec ranges: H 0-360, S/L 0-100. Iris only parses
                // integer h,s,l.
                $rgb = new Hsl(\sprintf(
                    '%d,%d,%d',
                    (int) round(self::normalizeHue($channels[0])),
                    (int) round(self::clamp($channels[1], 0.0, 100.0)),
                    (int) round(self::clamp($channels[2], 0.0, 100.0)),
                ))->toRgb();
            } else {
                // oklch: L is 0..1 per spec; iris expects a 0..100 percentage.
                $rgb = new Oklch(\sprintf(
                    '%s,%s,%s',
                    Number::format(self::clamp($channels[0], 0.0, 1.0) * 100),
                    Number::format($channels[1]),
                    Number::format(self::normalizeHue($channels[2])),
                ))->toRgb();
            }
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
