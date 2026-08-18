<?php

declare(strict_types=1);

namespace n5s\DtcgTokens;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Loader\TokenLoaderInterface;
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
        $result = ($parser ?? new TokenParser())
            ->parse($raw);

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
        return self::fromLoader(JsonFileLoader::fromPaths($paths), $parser);
    }

    /**
     * Build from any loader — the seam for custom sources (HTTP, database,
     * ...) that {@see self::fromFiles()} is a file-based shorthand for.
     */
    public static function fromLoader(TokenLoaderInterface $loader, ?TokenParser $parser = null): self
    {
        return self::fromArray($loader->load(), $parser);
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
     * Every mode name declared by at least one token in the collection.
     *
     * @return list<string>
     */
    public function modes(): array
    {
        /** @var array<string, true> $modes */
        $modes = [];
        foreach ($this->metadata as $metadata) {
            foreach ($metadata->modes as $mode) {
                $modes[$mode] = true;
            }
        }

        // strval: PHP canonicalizes numeric mode names ("2024") to int keys.
        return array_map(strval(...), array_keys($modes));
    }

    /**
     * A collection with every token bound to $mode — tokens that do not
     * declare it keep their base value. The building block for themed output:
     *
     *     $exporter->export($tokens->forMode('dark'));
     *
     * Descriptive metadata (description, deprecation) is retained, but the
     * per-token mode lists are cleared: the projection is terminal, so it
     * can no longer resolve any mode and must not claim otherwise. Project
     * each mode from the original collection.
     */
    public function forMode(string $mode): self
    {
        $values = [];
        foreach ($this->values as $path => $value) {
            $values[$path] = $value->forMode($mode);
        }

        // Mode-bound values carry no sibling map, so the projection can no
        // longer serve any mode: clearing the lists keeps modes() honest and
        // stops a second forMode() from looking like it worked.
        $metadata = [];
        foreach ($this->metadata as $path => $entry) {
            $metadata[$path] = new TokenMetadata($entry->description, $entry->deprecated, type: $entry->type);
        }

        return new self($values, $metadata);
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
     * @return \Traversable<string, TokenValueInterface>
     */
    public function getIterator(): \Traversable
    {
        // \Traversable, not \ArrayIterator: the concrete SPL class is an
        // implementation detail, not part of the published contract.
        return new \ArrayIterator($this->values);
    }

    public function count(): int
    {
        return \count($this->values);
    }
}
