<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Exception;

use n5s\DtcgTokens\Exception\TokenException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenException::class)]
final class TokenExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, TokenException::unknownPath('a.b'));
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
