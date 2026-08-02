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

    public function testNonReducibleSpaceThrowsInsteadOfSilentlyConvertingAsOklch(): void
    {
        // An unguarded else branch would treat any future space (e.g. hwb
        // added to SRGB_REDUCIBLE) as oklch — wrong colors, no exception.
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('not sRGB-reducible');

        SrgbConverter::toRgbChannels('hwb', [0.0, 0.0, 0.0]);
    }
}
