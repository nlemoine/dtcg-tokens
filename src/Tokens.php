<?php

declare(strict_types=1);

namespace n5s\DtcgTokens;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Parser\TokenMetadata;
use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Value\TokenValueInterface;

/**
 * @implements \IteratorAggregate<string, TokenValueInterface>
 */
final readonly class Tokens implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, TokenValueInterface> $values
     * @param array<string, TokenMetadata> $metadata
     */
    public function __construct(
        private array $values,
        private array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw, ?TokenParser $parser = null): self
    {
        $result = ($parser ?? new TokenParser())->parse($raw);

        return new self($result->values, $result->metadata);
    }

    public static function fromFile(string $path, ?TokenParser $parser = null): self
    {
        return self::fromFiles([$path], $parser);
    }

    /**
     * @param list<string> $paths
     */
    public static function fromFiles(array $paths, ?TokenParser $parser = null): self
    {
        return self::fromArray(JsonFileLoader::fromPaths($paths)->load(), $parser);
    }

    public function get(string $path, ?string $mode = null): TokenValueInterface
    {
        $token = $this->values[$path] ?? throw TokenException::unknownPath($path);

        return $mode !== null ? $token->forMode($mode) : $token;
    }

    public function has(string $path): bool
    {
        return isset($this->values[$path]);
    }

    /**
     * @return array<string, TokenValueInterface>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function metadata(string $path): ?TokenMetadata
    {
        return $this->metadata[$path] ?? null;
    }

    /**
     * @return array<string, TokenMetadata>
     */
    public function allMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return \ArrayIterator<string, TokenValueInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }

    public function count(): int
    {
        return \count($this->values);
    }
}
