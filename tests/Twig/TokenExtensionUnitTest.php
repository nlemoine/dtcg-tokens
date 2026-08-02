<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Twig;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Twig\TokenExtension;
use n5s\DtcgTokens\Value\DimensionValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenExtension::class)]
final class TokenExtensionUnitTest extends TestCase
{
    public function testHexOnNonColorThrowsTokenException(): void
    {
        $extension = new TokenExtension(new Tokens([]));

        // The library's exception contract: consumers catch TokenException.
        $this->expectException(TokenException::class);
        $this->expectExceptionMessage(
            \sprintf('The "hex" filter can only be used on color tokens, got %s.', DimensionValue::class),
        );

        $extension->hex(new DimensionValue(16.0, 'px'));
    }

    public function testRgbOnNonColorThrowsTokenException(): void
    {
        $extension = new TokenExtension(new Tokens([]));

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage(
            \sprintf('The "rgb" filter can only be used on color tokens, got %s.', DimensionValue::class),
        );

        $extension->rgb(new DimensionValue(16.0, 'px'));
    }
}
