<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Internal;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\SrgbConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrgbConverter::class)]
final class SrgbConverterTest extends TestCase
{
    public function testSrgbChannelsScaleToEightBit(): void
    {
        self::assertSame([255, 0, 128], SrgbConverter::toRgbChannels('srgb', [1.0, 0.0, 0.502]));
    }

    public function testFractionalHslComponentsAreNotRoundedAway(): void
    {
        // DTCG channels are numeric, not integer-only: quantization belongs
        // on the final 8-bit RGB channels, never on the authored input.
        $low = SrgbConverter::toRgbChannels('hsl', [0.0, 100.0, 20.0]);
        $high = SrgbConverter::toRgbChannels('hsl', [0.0, 100.0, 20.4]);

        self::assertNotSame($low, $high);
    }

    public function testHslConversionMatchesKnownCssValues(): void
    {
        // hsl(210 100% 50%) is rgb(0 127.5 255); browsers round half up.
        self::assertSame([0, 128, 255], SrgbConverter::toRgbChannels('hsl', [210.0, 100.0, 50.0]));
        self::assertSame([255, 0, 0], SrgbConverter::toRgbChannels('hsl', [0.0, 100.0, 50.0]));
        self::assertSame([0, 255, 0], SrgbConverter::toRgbChannels('hsl', [120.0, 100.0, 50.0]));
        self::assertSame([0, 0, 255], SrgbConverter::toRgbChannels('hsl', [240.0, 100.0, 50.0]));
        // Achromatic: saturation 0 is grey at any hue.
        self::assertSame([128, 128, 128], SrgbConverter::toRgbChannels('hsl', [123.0, 0.0, 50.2]));
        self::assertSame([0, 0, 0], SrgbConverter::toRgbChannels('hsl', [0.0, 100.0, 0.0]));
        self::assertSame([255, 255, 255], SrgbConverter::toRgbChannels('hsl', [0.0, 100.0, 100.0]));
    }

    public function testHslHueIsWrappedAndComponentsClamped(): void
    {
        self::assertSame(
            SrgbConverter::toRgbChannels('hsl', [210.0, 100.0, 50.0]),
            SrgbConverter::toRgbChannels('hsl', [-150.0, 150.0, 50.0]),
        );
    }

    public function testNonReducibleSpaceThrowsInsteadOfSilentlyConvertingAsOklch(): void
    {
        // An unguarded else branch would treat any future space (e.g. hwb
        // added to SRGB_REDUCIBLE) as oklch — wrong colors, no exception.
        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('not sRGB-reducible');

        SrgbConverter::toRgbChannels('hwb', [0.0, 0.0, 0.0]);
    }
}
