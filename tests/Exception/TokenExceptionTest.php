<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Exception;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Exception\TokenFileException;
use n5s\DtcgTokens\Exception\TokenNotFoundException;
use n5s\DtcgTokens\Exception\TokenParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenException::class)]
#[CoversClass(TokenFileException::class)]
#[CoversClass(TokenNotFoundException::class)]
#[CoversClass(TokenParseException::class)]
final class TokenExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, TokenException::unknownPath('a.b'));
    }

    public function testLookupFailureIsATokenNotFoundException(): void
    {
        self::assertInstanceOf(TokenNotFoundException::class, TokenException::unknownPath('a.b'));
    }

    public function testFileFailuresAreTokenFileExceptions(): void
    {
        // "Fix your deployment" errors, distinguishable from token-content ones.
        self::assertInstanceOf(TokenFileException::class, TokenException::fileNotReadable('/x.json'));
        self::assertInstanceOf(TokenFileException::class, TokenException::notAnObject('/x.json', 'string'));
        self::assertInstanceOf(TokenFileException::class, TokenException::invalidJson('/x.json', new \JsonException('boom')));
    }

    public function testTokenContentFailuresAreTokenParseExceptions(): void
    {
        self::assertInstanceOf(TokenParseException::class, TokenException::invalidValue('boom'));
        self::assertInstanceOf(TokenParseException::class, TokenException::brokenAlias('{x}', 'a'));
        self::assertInstanceOf(TokenParseException::class, TokenException::circularAlias('a', ['a']));
        self::assertInstanceOf(TokenParseException::class, TokenException::unsupportedType('weird'));
        self::assertInstanceOf(TokenParseException::class, TokenException::unsupportedColorSpace('cmyk'));
        self::assertInstanceOf(TokenParseException::class, TokenException::duplicatePath('a.b'));
        self::assertInstanceOf(TokenParseException::class, TokenException::colorConversionFailed('oklch', new \RuntimeException('x')));
        self::assertInstanceOf(TokenParseException::class, TokenException::inToken('a.b', TokenException::invalidValue('boom')));
    }

    public function testEverySubtypeRemainsCatchableAsTokenException(): void
    {
        self::assertInstanceOf(TokenException::class, TokenException::unknownPath('a'));
        self::assertInstanceOf(TokenException::class, TokenException::fileNotReadable('/x'));
        self::assertInstanceOf(TokenException::class, TokenException::invalidValue('x'));
    }

    public function testUnknownPath(): void
    {
        self::assertSame(
            'Design token "color.base" not found.',
            TokenException::unknownPath('color.base')->getMessage(),
        );
    }

    public function testBrokenAlias(): void
    {
        self::assertSame(
            'Alias "{color.x}" in token "color.fg" could not be resolved.',
            TokenException::brokenAlias('{color.x}', 'color.fg')->getMessage(),
        );
    }

    public function testCircularAlias(): void
    {
        self::assertSame(
            'Circular alias detected at "a": a → b → a',
            TokenException::circularAlias('a', ['a', 'b', 'a'])->getMessage(),
        );
    }

    public function testUnsupportedType(): void
    {
        self::assertSame(
            'Unsupported token type "weird".',
            TokenException::unsupportedType('weird')->getMessage(),
        );
    }

    public function testUnsupportedTypeNull(): void
    {
        self::assertSame(
            'Unsupported token type "null".',
            TokenException::unsupportedType(null)->getMessage(),
        );
    }

    public function testUnsupportedColorSpace(): void
    {
        self::assertSame(
            'Unsupported color space "cmyk".',
            TokenException::unsupportedColorSpace('cmyk')->getMessage(),
        );
    }

    public function testInvalidValue(): void
    {
        self::assertSame('boom', TokenException::invalidValue('boom')->getMessage());
    }

    public function testFileNotReadable(): void
    {
        self::assertSame(
            'Cannot read token file "/tmp/missing.json".',
            TokenException::fileNotReadable('/tmp/missing.json')->getMessage(),
        );
    }

    public function testNotAnObject(): void
    {
        self::assertSame(
            'Token file "/tmp/tokens.json" must contain a JSON object, got string.',
            TokenException::notAnObject('/tmp/tokens.json', 'string')->getMessage(),
        );
    }

    public function testInvalidJsonCarriesPreviousException(): void
    {
        $previous = new \JsonException('Syntax error');
        $exception = TokenException::invalidJson('/tmp/tokens.json', $previous);

        self::assertSame(
            'Token file "/tmp/tokens.json" contains invalid JSON: Syntax error',
            $exception->getMessage(),
        );
        self::assertSame($previous, $exception->getPrevious());
    }
}
