<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Exception;

final class TokenException extends \RuntimeException
{
    public static function unknownPath(string $path): self
    {
        return new self(\sprintf('Design token "%s" not found.', $path));
    }

    public static function brokenAlias(string $alias, string $tokenPath): self
    {
        return new self(\sprintf('Alias "%s" in token "%s" could not be resolved.', $alias, $tokenPath));
    }

    /**
     * @param list<string> $chain
     */
    public static function circularAlias(string $path, array $chain): self
    {
        return new self(\sprintf('Circular alias detected at "%s": %s', $path, implode(' → ', $chain)));
    }

    public static function unsupportedType(?string $type): self
    {
        return new self(\sprintf('Unsupported token type "%s".', $type ?? 'null'));
    }

    public static function unsupportedColorSpace(string $colorSpace): self
    {
        return new self(\sprintf('Unsupported color space "%s".', $colorSpace));
    }

    public static function invalidValue(string $message): self
    {
        return new self($message);
    }

    public static function fileNotReadable(string $path): self
    {
        return new self(\sprintf('Cannot read token file "%s".', $path));
    }

    public static function notAnObject(string $path, string $actualType): self
    {
        return new self(\sprintf(
            'Token file "%s" must contain a JSON object, got %s.',
            $path,
            $actualType,
        ));
    }

    public static function invalidJson(string $path, \JsonException $previous): self
    {
        return new self(
            \sprintf('Token file "%s" contains invalid JSON: %s', $path, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
