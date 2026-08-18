<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

use n5s\DtcgTokens\Exception\TokenException;

/**
 * Resolves alias references ({some.path}) and mode hoisting over a flattened
 * entry map.
 *
 * One instance per parse: resolution is a pure function of (path, mode) —
 * the entry map is immutable — so completed results are memoized. Without
 * this, a token referenced from several fields of a composite re-walks its
 * whole subtree per reference and resolution degenerates to
 * O(fan-out^depth).
 *
 * @internal
 *
 * @phpstan-import-type RawEntry from TokenParser
 */
final class AliasResolver
{
    /**
     * Alias pattern: {some.path}. The D modifier keeps $ from matching before
     * a trailing newline — "{x}\n" is a literal string, not an alias.
     */
    private const string ALIAS_PATTERN = '/^\{(.+)}$/D';

    /**
     * Alias chains link laterally across the flat entry map, so json_decode's
     * nesting cap does not bound them. Each hop keeps a frame alive, so an
     * unbounded chain dies as an uncatchable OOM fatal instead of a
     * TokenException. Real design systems nest a handful of levels.
     */
    private const int MAX_CHAIN_DEPTH = 100;

    /**
     * @var array<string, mixed> Completed base resolutions, keyed by path
     */
    private array $resolvedBase = [];

    /**
     * @var array<string, array<string, mixed>> Completed resolutions per mode, then path
     */
    private array $resolvedByMode = [];

    /**
     * @var array<string, list<string>> Completed mode sets per path
     */
    private array $modesMemo = [];

    /**
     * @param array<array-key, RawEntry> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {
    }

    /**
     * Resolve a token's value for a given mode (null = base value), following
     * aliases in that same mode and falling back to a referenced token's base
     * value when it does not itself declare the mode.
     */
    public function resolve(string $path, ?string $mode): mixed
    {
        return $this->resolveInMode($path, $mode, []);
    }

    /**
     * The full set of mode names a token resolves to: its own declared modes,
     * or - when it declares none - the modes hoisted from the tokens it aliases
     * (directly or nested inside a composite value), resolved transitively.
     *
     * @return list<string>
     */
    public function effectiveModes(string $path): array
    {
        return $this->modesOf($path, []);
    }

    /**
     * @param array<string, true> $chain Visited token paths (insertion-ordered), for cycle detection
     */
    private function resolveInMode(string $path, ?string $mode, array $chain): mixed
    {
        if (isset($chain[$path])) {
            throw TokenException::circularAlias($path, array_map(strval(...), array_keys($chain)));
        }

        if (\count($chain) >= self::MAX_CHAIN_DEPTH) {
            throw TokenException::aliasChainTooDeep($path, self::MAX_CHAIN_DEPTH);
        }

        // Separate memos per mode: one keyed map would need a separator that
        // cannot occur in a mode name, and none is guaranteed.
        if ($mode === null) {
            if (\array_key_exists($path, $this->resolvedBase)) {
                return $this->resolvedBase[$path];
            }
        } elseif (\array_key_exists($path, $this->resolvedByMode[$mode] ?? [])) {
            return $this->resolvedByMode[$mode][$path];
        }

        $entry = $this->entries[$path];
        $raw = $mode !== null && isset($entry['modes']) && \array_key_exists($mode, $entry['modes'])
            ? $entry['modes'][$mode]
            : $entry['value'];

        $chain[$path] = true;

        // Memoize completed resolutions only: a cycle throws before this line,
        // so no partial result can ever be cached.
        $resolved = $this->resolveValue($raw, $mode, $chain);

        if ($mode === null) {
            return $this->resolvedBase[$path] = $resolved;
        }

        return $this->resolvedByMode[$mode][$path] = $resolved;
    }

    /**
     * Resolve any aliases contained in a raw value, each in the given mode.
     *
     * @param array<string, true> $chain
     */
    private function resolveValue(mixed $value, ?string $mode, array $chain): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolveValue($v, $mode, $chain);
            }

            return $value;
        }

        if (! \is_string($value)) {
            return $value;
        }

        if (preg_match(self::ALIAS_PATTERN, $value, $matches) === 1) {
            $aliasPath = $matches[1];

            if (! isset($this->entries[$aliasPath])) {
                // The chain is insertion-ordered from the walk root; the token
                // actually holding the broken alias is the LAST one entered.
                // Cast: PHP canonicalizes a numeric path key ("100") to int.
                $holder = array_key_last($chain);

                throw TokenException::brokenAlias($value, $holder === null ? $aliasPath : (string) $holder);
            }

            return $this->resolveInMode($aliasPath, $mode, $chain);
        }

        return $value;
    }

    /**
     * @param array<string, true> $visiting Cycle guard (insertion-ordered)
     *
     * @return list<string>
     */
    private function modesOf(string $path, array $visiting): array
    {
        if (isset($visiting[$path])) {
            // Unreachable in practice: TokenParser resolves every value
            // (throwing on any alias cycle) before mode hoisting runs.
            // Throwing keeps the memo free of cycle-truncated results should
            // that ordering change.
            throw TokenException::circularAlias($path, array_map(strval(...), array_keys($visiting)));
        }

        if (isset($this->modesMemo[$path])) {
            return $this->modesMemo[$path];
        }

        $entry = $this->entries[$path];
        if (isset($entry['modes']) && $entry['modes'] !== []) {
            // PHP canonicalizes numeric JSON keys ("2024") to ints; mode names
            // are strings everywhere downstream.
            return $this->modesMemo[$path] = array_map(strval(...), array_keys($entry['modes']));
        }

        /** @var array<string, true> $modes */
        $modes = [];
        $visiting[$path] = true;
        foreach ($this->collectAliasTargets($entry['value']) as $target) {
            if (! isset($this->entries[$target])) {
                continue;
            }

            foreach ($this->modesOf($target, $visiting) as $mode) {
                $modes[$mode] = true;
            }
        }

        return $this->modesMemo[$path] = array_map(strval(...), array_keys($modes));
    }

    /**
     * Collect every alias target path referenced anywhere within a raw value.
     *
     * @return list<string>
     */
    private function collectAliasTargets(mixed $value): array
    {
        if (\is_array($value)) {
            $targets = [];
            foreach ($value as $v) {
                foreach ($this->collectAliasTargets($v) as $target) {
                    $targets[] = $target;
                }
            }

            return $targets;
        }

        if (\is_string($value) && preg_match(self::ALIAS_PATTERN, $value, $matches) === 1) {
            return [$matches[1]];
        }

        return [];
    }
}
