<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Exception;

use n5s\DtcgTokens\Internal\Str;

/**
 * Base type of every failure this library raises — `catch (TokenException)`
 * always works. The static factories return domain-specific subtypes for
 * differentiated handling: {@see TokenNotFoundException} (lookup),
 * {@see TokenFileException} (source I/O), {@see TokenParseException}
 * (token content).
 */
class TokenException extends \RuntimeException
{
    public static function unknownPath(string $path): TokenNotFoundException
    {
        return new TokenNotFoundException(\sprintf('Design token "%s" not found.', Str::excerpt($path)));
    }

    public static function inToken(string $path, self $previous): TokenParseException
    {
        return new TokenParseException(
            \sprintf('Token "%s": %s', Str::excerpt($path), $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function duplicatePath(string $path): TokenParseException
    {
        return new TokenParseException(\sprintf(
            'Duplicate token path "%s": two definitions (e.g. a flat "a.b" key and a nested group) collapse to the same path.',
            Str::excerpt($path),
        ));
    }

    public static function brokenAlias(string $alias, string $tokenPath): TokenParseException
    {
        return new TokenParseException(\sprintf('Alias "%s" in token "%s" could not be resolved.', Str::excerpt($alias), Str::excerpt($tokenPath)));
    }

    /**
     * @param list<string> $chain
     */
    public static function circularAlias(string $path, array $chain): TokenParseException
    {
        return new TokenParseException(\sprintf(
            'Circular alias detected at "%s": %s',
            Str::excerpt($path),
            Str::excerpt(implode(' → ', $chain)),
        ));
    }

    public static function aliasChainTooDeep(string $path, int $limit): TokenParseException
    {
        return new TokenParseException(\sprintf(
            'Alias chain is too deep at "%s": more than %d hops. Flatten the chain, or check for an unintended reference.',
            Str::excerpt($path),
            $limit,
        ));
    }

    public static function unsupportedType(?string $type): TokenParseException
    {
        return new TokenParseException(\sprintf('Unsupported token type "%s".', $type === null ? 'null' : Str::excerpt($type)));
    }

    public static function unsupportedColorSpace(string $colorSpace): TokenParseException
    {
        return new TokenParseException(\sprintf('Unsupported color space "%s".', Str::excerpt($colorSpace)));
    }

    public static function colorConversionFailed(string $colorSpace, \Throwable $previous): TokenParseException
    {
        return new TokenParseException(
            \sprintf('Cannot convert color in space "%s" to sRGB: %s', $colorSpace, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function invalidValue(string $message): TokenParseException
    {
        return new TokenParseException($message);
    }

    public static function fileNotReadable(string $path): TokenFileException
    {
        return new TokenFileException(\sprintf('Cannot read token file "%s".', Str::excerpt($path)));
    }

    public static function notAnObject(string $path, string $actualType): TokenFileException
    {
        return new TokenFileException(\sprintf(
            'Token file "%s" must contain a JSON object, got %s.',
            Str::excerpt($path),
            $actualType,
        ));
    }

    public static function invalidJson(string $path, \JsonException $previous): TokenFileException
    {
        return new TokenFileException(
            \sprintf('Token file "%s" contains invalid JSON: %s', Str::excerpt($path), $previous->getMessage()),
            0,
            $previous,
        );
    }
}
