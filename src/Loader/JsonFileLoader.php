<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Loader;

use n5s\DtcgTokens\Exception\TokenException;

final readonly class JsonFileLoader implements TokenLoaderInterface
{
    /**
     * @var list<string>
     */
    private array $filePaths;

    public function __construct(string ...$filePaths)
    {
        // array_values() is a no-op at runtime (a variadic is already a list),
        // but it narrows the param to list<string> for static analysis.
        $this->filePaths = array_values($filePaths);
    }

    /**
     * @param list<string> $paths
     */
    public static function fromPaths(array $paths): self
    {
        return new self(...$paths);
    }

    public function load(): array
    {
        $merged = [];
        foreach ($this->filePaths as $filePath) {
            $json = file_get_contents($filePath);
            if ($json === false) {
                throw TokenException::fileNotReadable($filePath);
            }

            try {
                $raw = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw TokenException::invalidJson($filePath, $e);
            }

            if (! \is_array($raw)) {
                throw TokenException::notAnObject($filePath, get_debug_type($raw));
            }

            /** @var array<string, mixed> $raw */
            $merged = $this->deepMerge($merged, $raw);
        }

        /** @var array<string, mixed> $merged */
        return $merged;
    }

    public function maxMtime(): int
    {
        $max = 0;
        foreach ($this->filePaths as $filePath) {
            $mtime = @filemtime($filePath);
            if ($mtime !== false && $mtime > $max) {
                $max = $mtime;
            }
        }

        return $max;
    }

    /**
     * A stable identifier for this loader's source set, for use as a cache-key
     * component. Order-sensitive (matching merge order): two loaders over the
     * same paths share it, different paths produce different values.
     *
     * @internal
     */
    public function fingerprint(): string
    {
        return hash('xxh128', implode("\0", $this->filePaths));
    }

    /**
     * Deep-merge two raw DTCG trees. Object maps merge recursively, so separate
     * files can contribute sibling tokens to a shared group; a token's `$value`
     * - and any list or scalar - is replaced wholesale by the later file, so a
     * shorter list or a redefined value never leaves stale entries behind
     * (unlike array_replace_recursive, which merges lists element-wise).
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     *
     * @return array<array-key, mixed>
     */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $existing = $base[$key] ?? null;

            if (
                $key !== '$value'
                && \is_array($existing)
                && \is_array($value)
                && ! array_is_list($existing)
                && ! array_is_list($value)
            ) {
                $base[$key] = $this->deepMerge($existing, $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
