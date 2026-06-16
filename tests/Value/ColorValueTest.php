<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Value;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Value\ColorValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColorValue::class)]
final class ColorValueTest extends TestCase
{
    public function testSixDigitHexToHex(): void
    {
        self::assertSame('#ff0000', ColorValue::fromHex('#ff0000')->toHex());
    }

    public function testSixDigitHexToRgb(): void
    {
        self::assertSame('rgb(255 0 0)', ColorValue::fromHex('#ff0000')->toRgb());
    }

    public function testEightDigitHexToRgbContainsAlpha(): void
    {
        self::assertStringContainsString('/ 0.5', ColorValue::fromHex('#ff000080')->toRgb());
    }

    public function testHexToCssMatchesRgb(): void
    {
        self::assertSame('rgb(255 0 0)', ColorValue::fromHex('#ff0000')->toCss());
        self::assertSame('rgb(255 0 0)', (string) ColorValue::fromHex('#ff0000'));
    }

    public function testHexPreservesOriginalStringLowercased(): void
    {
        self::assertSame('#ff0000', ColorValue::fromHex('#FF0000')->toHex());
    }

    public function testSrgbFromComponents(): void
    {
        $color = ColorValue::fromComponents('srgb', [1.0, 0.0, 0.0], 1.0);

        self::assertSame('rgb(255 0 0)', $color->toCss());
        self::assertSame('rgb(255 0 0)', (string) $color);
        self::assertSame('#ff0000', $color->toHex());
    }

    public function testSrgbWithAlphaToCss(): void
    {
        $color = ColorValue::fromComponents('srgb', [1.0, 0.0, 0.0], 0.5);

        self::assertSame('rgb(255 0 0 / 0.5)', $color->toCss());
    }

    public function testHslPureRedConvertsToKnownHex(): void
    {
        $color = ColorValue::fromComponents('hsl', [0.0, 1.0, 0.5], 1.0);

        self::assertSame('#ff0000', $color->toHex());
    }

    public function testHslToCss(): void
    {
        $color = ColorValue::fromComponents('hsl', [0.0, 1.0, 0.5], 1.0);

        self::assertSame('hsl(0 100% 50%)', $color->toCss());
    }

    public function testHwbToCss(): void
    {
        $color = ColorValue::fromComponents('hwb', [0.0, 0.0, 0.0], 1.0);

        self::assertSame('hwb(0 0% 0%)', $color->toCss());
    }

    public function testToRgbClampsAlphaAboveOneToOpaque(): void
    {
        self::assertSame('rgb(255 0 0)', ColorValue::fromHex('#ff0000')->toRgb(1.5));
    }

    public function testToRgbClampsNegativeAlphaToZero(): void
    {
        self::assertSame('rgb(255 0 0 / 0)', ColorValue::fromHex('#ff0000')->toRgb(-0.5));
    }

    public function testOklchToHexViaIris(): void
    {
        $color = ColorValue::fromComponents('oklch', [0.7, 0.15, 30.0], 1.0);

        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color->toHex());
    }

    public function testOklchToCss(): void
    {
        $color = ColorValue::fromComponents('oklch', [0.7, 0.15, 30.0], 1.0);

        self::assertSame('oklch(0.7 0.15 30)', $color->toCss());
    }

    public function testDisplayP3ToCssIsNotSquashedToSrgb(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0);

        self::assertSame('color(display-p3 1 0 0)', $color->toCss());
    }

    public function testDisplayP3WithoutHexFallbackThrowsOnToHex(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0);

        $this->expectException(TokenException::class);
        $color->toHex();
    }

    public function testDisplayP3WithoutHexFallbackThrowsOnToRgb(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0);

        $this->expectException(TokenException::class);
        $color->toRgb();
    }

    public function testDisplayP3WithHexFallbackUsesFallbackForHex(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0, '#fd0000');

        self::assertSame('#fd0000', $color->toHex());
    }

    public function testDisplayP3WithHexFallbackDerivesRgbFromHex(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0, '#fd0000');

        self::assertSame('rgb(253 0 0)', $color->toRgb());
    }

    public function testOklabToCss(): void
    {
        $color = ColorValue::fromComponents('oklab', [0.7, 0.1, -0.05], 1.0);

        self::assertSame('oklab(0.7 0.1 -0.05)', $color->toCss());
    }

    public function testLabToCss(): void
    {
        $color = ColorValue::fromComponents('lab', [50.0, 40.0, 30.0], 1.0);

        self::assertSame('lab(50 40 30)', $color->toCss());
    }

    public function testLabD65ToCss(): void
    {
        $color = ColorValue::fromComponents('lab-d65', [50.0, 40.0, 30.0], 1.0);

        self::assertSame('lab(50 40 30)', $color->toCss());
    }

    public function testLchToCss(): void
    {
        $color = ColorValue::fromComponents('lch', [50.0, 40.0, 120.0], 1.0);

        self::assertSame('lch(50 40 120)', $color->toCss());
    }

    public function testRec2020ToCss(): void
    {
        $color = ColorValue::fromComponents('rec2020', [0.5, 0.2, 0.1], 1.0);

        self::assertSame('color(rec2020 0.5 0.2 0.1)', $color->toCss());
    }

    public function testA98RgbToCss(): void
    {
        $color = ColorValue::fromComponents('a98-rgb', [0.5, 0.2, 0.1], 1.0);

        self::assertSame('color(a98-rgb 0.5 0.2 0.1)', $color->toCss());
    }

    public function testProphotoRgbToCss(): void
    {
        $color = ColorValue::fromComponents('prophoto-rgb', [0.5, 0.2, 0.1], 1.0);

        self::assertSame('color(prophoto-rgb 0.5 0.2 0.1)', $color->toCss());
    }

    public function testSrgbLinearToCss(): void
    {
        $color = ColorValue::fromComponents('srgb-linear', [0.5, 0.2, 0.1], 1.0);

        self::assertSame('color(srgb-linear 0.5 0.2 0.1)', $color->toCss());
    }

    public function testXyzToCss(): void
    {
        $color = ColorValue::fromComponents('xyz', [0.4, 0.2, 0.1], 1.0);

        self::assertSame('color(xyz 0.4 0.2 0.1)', $color->toCss());
    }

    public function testXyzD50ToCss(): void
    {
        $color = ColorValue::fromComponents('xyz-d50', [0.4, 0.2, 0.1], 1.0);

        self::assertSame('color(xyz-d50 0.4 0.2 0.1)', $color->toCss());
    }

    public function testXyzD65ToCss(): void
    {
        $color = ColorValue::fromComponents('xyz-d65', [0.4, 0.2, 0.1], 1.0);

        self::assertSame('color(xyz-d65 0.4 0.2 0.1)', $color->toCss());
    }

    public function testNullChannelRendersAsNone(): void
    {
        $color = ColorValue::fromComponents('oklch', [0.7, 0.15, null], 1.0);

        self::assertStringContainsString('none', $color->toCss());
        self::assertSame('oklch(0.7 0.15 none)', $color->toCss());
    }

    public function testAlphaInColorFunctionSpaceAppended(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 0.5);

        self::assertSame('color(display-p3 1 0 0 / 0.5)', $color->toCss());
    }

    public function testAlphaInLabSpaceAppended(): void
    {
        $color = ColorValue::fromComponents('lab', [50.0, 40.0, 30.0], 0.5);

        self::assertSame('lab(50 40 30 / 0.5)', $color->toCss());
    }

    public function testOkhsvToCssUsesHexFallbackWhenAvailable(): void
    {
        $color = ColorValue::fromComponents('okhsv', [0.5, 0.5, 0.5], 1.0, '#336699');

        self::assertSame('#336699', $color->toCss());
    }

    public function testOkhsvToCssThrowsWithoutHexFallback(): void
    {
        $color = ColorValue::fromComponents('okhsv', [0.5, 0.5, 0.5], 1.0);

        $this->expectException(TokenException::class);
        $color->toCss();
    }

    public function testUnknownColorSpaceThrows(): void
    {
        $this->expectException(TokenException::class);
        ColorValue::fromComponents('not-a-space', [0.0, 0.0, 0.0], 1.0);
    }

    public function testFromComponentsRejectsFewerThanThreeComponents(): void
    {
        $this->expectException(TokenException::class);
        ColorValue::fromComponents('srgb', [1.0, 0.0], 1.0);
    }

    public function testFourComponentListRendersThreeChannels(): void
    {
        // A stray 4th component must not corrupt the lab() output.
        $color = ColorValue::fromComponents('lab', [50.0, 40.0, 30.0, 99.0], 1.0);

        self::assertSame('lab(50 40 30)', $color->toCss());
    }

    public function testFourComponentColorFunctionRendersThreeChannels(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0, 99.0], 1.0);

        self::assertSame('color(display-p3 1 0 0)', $color->toCss());
    }

    public function testAccessorsReturnStoredValues(): void
    {
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, null], 0.5, '#fd0000');

        self::assertSame('display-p3', $color->colorSpace());
        self::assertSame([1.0, 0.0, null], $color->components());
        self::assertSame(0.5, $color->alpha());
        self::assertSame('#fd0000', $color->hex());
    }

    public function testForModeReturnsModeWhenPresent(): void
    {
        $dark = ColorValue::fromHex('#000000');
        $light = ColorValue::fromHex('#ffffff', [
            'dark' => $dark,
        ]);

        self::assertSame($dark, $light->forMode('dark'));
    }

    public function testForModeReturnsSelfWhenMissing(): void
    {
        $light = ColorValue::fromHex('#ffffff');

        self::assertSame($light, $light->forMode('dark'));
    }

    public function testSrgbComponentsWithAlphaToHexEmitsEightDigitForm(): void
    {
        // No stored hex fallback + alpha < 1 -> toHex() derives the 8-digit form.
        $color = ColorValue::fromComponents('srgb', [1.0, 0.0, 0.0], 0.5);

        self::assertSame('#ff000080', $color->toHex());
    }

    public function testNonReducibleSpaceWithEightDigitHexFallbackDerivesRgbFromStrippedHex(): void
    {
        // display-p3 is not sRGB-reducible, so toRgb() goes through the hex
        // fallback; an 8-digit fallback must be stripped to 6 digits for Iris.
        $color = ColorValue::fromComponents('display-p3', [1.0, 0.0, 0.0], 1.0, '#fd000080');

        self::assertSame('rgb(253 0 0)', $color->toRgb());
    }
}
