<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Value\BooleanValue;
use n5s\DtcgTokens\Value\BorderValue;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\CubicBezierValue;
use n5s\DtcgTokens\Value\DimensionValue;
use n5s\DtcgTokens\Value\FontFamilyValue;
use n5s\DtcgTokens\Value\GradientValue;
use n5s\DtcgTokens\Value\LinkValue;
use n5s\DtcgTokens\Value\NumberValue;
use n5s\DtcgTokens\Value\ShadowValue;
use n5s\DtcgTokens\Value\StringValue;
use n5s\DtcgTokens\Value\StrokeStyleValue;
use n5s\DtcgTokens\Value\TokenValueInterface;
use n5s\DtcgTokens\Value\TransitionValue;
use n5s\DtcgTokens\Value\TypographyValue;

/**
 * @phpstan-type RawEntry array{type: string|null, value: mixed, modes?: array<string, mixed>, description: string|null, deprecated: bool}
 */
final class TokenParser
{
    private const array FONT_WEIGHT_MAP = [
        'thin' => 100,
        'hairline' => 100,
        'extra-light' => 200,
        'ultra-light' => 200,
        'light' => 300,
        'normal' => 400,
        'regular' => 400,
        'book' => 400,
        'medium' => 500,
        'semi-bold' => 600,
        'demi-bold' => 600,
        'bold' => 700,
        'extra-bold' => 800,
        'ultra-bold' => 800,
        'black' => 900,
        'heavy' => 900,
        'extra-black' => 950,
        'ultra-black' => 950,
    ];

    private const array DIMENSION_UNITS = ['px', 'rem', 'em'];

    private const array DURATION_UNITS = ['ms', 's'];

    private const array LINE_CAPS = ['round', 'butt', 'square'];

    /**
     * Parse a raw DTCG token tree into typed value objects plus metadata.
     *
     * @param array<string, mixed> $raw
     */
    public function parse(array $raw): ParseResult
    {
        // Step 1: Flatten tree into raw entries with inherited $type and $deprecated
        /** @var array<string, RawEntry> $entries */
        $entries = [];
        $this->walkTree($raw, '', null, false, $entries);

        // Step 2: Resolve each token in every mode it (transitively) supports,
        // then build typed value objects carrying their per-mode siblings.
        /** @var array<string, TokenValueInterface> $tokens */
        $tokens = [];
        /** @var array<string, TokenMetadata> $metadata */
        $metadata = [];
        foreach ($entries as $path => $entry) {
            $base = $this->resolveInMode($path, null, $entries, []);

            $modeValues = null;
            $modeSet = $this->effectiveModes($path, $entries, []);
            if ($modeSet !== []) {
                $modeValues = [];
                foreach ($modeSet as $mode) {
                    $modeValues[$mode] = $this->resolveInMode($path, $mode, $entries, []);
                }
            }

            $tokens[$path] = $this->buildValue($entry['type'], $base, $modeValues);
            $metadata[$path] = new TokenMetadata($entry['description'], $entry['deprecated']);
        }

        return new ParseResult($tokens, $metadata);
    }

    /**
     * Walk the JSON tree, collecting token entries with inherited $type and $deprecated.
     *
     * @param array<string, mixed> $node
     * @param array<string, RawEntry> $entries
     */
    private function walkTree(array $node, string $prefix, ?string $inheritedType, bool $inheritedDeprecated, array &$entries): void
    {
        /** @var string|null $groupType */
        $groupType = $node['$type'] ?? $inheritedType;
        $groupDeprecated = \array_key_exists('$deprecated', $node)
            ? $this->normalizeDeprecated($node['$deprecated'])
            : $inheritedDeprecated;

        foreach ($node as $key => $child) {
            $key = (string) $key;

            if (str_starts_with($key, '$')) {
                continue;
            }

            if (! \is_array($child)) {
                continue;
            }

            /** @var array<string, mixed> $child */
            $path = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\array_key_exists('$value', $child)) {
                $description = $child['$description'] ?? null;

                /** @var RawEntry $entry */
                $entry = [
                    'type' => $child['$type'] ?? $groupType,
                    'value' => $child['$value'],
                    'description' => \is_string($description) ? $description : null,
                    'deprecated' => \array_key_exists('$deprecated', $child)
                        ? $this->normalizeDeprecated($child['$deprecated'])
                        : $groupDeprecated,
                ];

                if (isset($child['$extensions']) && \is_array($child['$extensions']) && isset($child['$extensions']['mode'])) {
                    /** @var array<string, mixed> $mode */
                    $mode = $child['$extensions']['mode'];
                    $entry['modes'] = $mode;
                }

                $entries[$path] = $entry;
            } else {
                $this->walkTree($child, $path, \is_string($groupType) ? $groupType : null, $groupDeprecated, $entries);
            }
        }
    }

    /**
     * Normalize a DTCG `$deprecated` value (bool or message string) to a bool.
     */
    private function normalizeDeprecated(mixed $value): bool
    {
        if (\is_string($value)) {
            return $value !== '';
        }

        return (bool) $value;
    }

    /**
     * Resolve a token's value for a given mode (null = base value), following
     * aliases in that same mode and falling back to a referenced token's base
     * value when it does not itself declare the mode.
     *
     * @param array<string, RawEntry> $entries
     * @param list<string> $chain Visited token paths, for cycle detection
     */
    private function resolveInMode(string $path, ?string $mode, array $entries, array $chain): mixed
    {
        if (\in_array($path, $chain, true)) {
            throw TokenException::circularAlias($path, $chain);
        }

        $entry = $entries[$path];
        $raw = $mode !== null && isset($entry['modes']) && \array_key_exists($mode, $entry['modes'])
            ? $entry['modes'][$mode]
            : $entry['value'];

        return $this->resolveValueInMode($raw, $mode, $entries, [...$chain, $path]);
    }

    /**
     * Resolve any aliases contained in a raw value, each in the given mode.
     *
     * @param array<string, RawEntry> $entries
     * @param list<string> $chain
     */
    private function resolveValueInMode(mixed $value, ?string $mode, array $entries, array $chain): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolveValueInMode($v, $mode, $entries, $chain);
            }

            return $value;
        }

        if (! \is_string($value)) {
            return $value;
        }

        // Check for alias pattern: {some.path}
        if (preg_match('/^\{(.+)}$/', $value, $matches) === 1) {
            $aliasPath = $matches[1];

            if (! isset($entries[$aliasPath])) {
                throw TokenException::brokenAlias($value, $chain[0] ?? $aliasPath);
            }

            return $this->resolveInMode($aliasPath, $mode, $entries, $chain);
        }

        return $value;
    }

    /**
     * The full set of mode names a token resolves to: its own declared modes,
     * or - when it declares none - the modes hoisted from the tokens it aliases
     * (directly or nested inside a composite value), resolved transitively.
     *
     * @param array<string, RawEntry> $entries
     * @param list<string> $visiting Cycle guard
     *
     * @return list<string>
     */
    private function effectiveModes(string $path, array $entries, array $visiting): array
    {
        if (\in_array($path, $visiting, true)) {
            return [];
        }

        $entry = $entries[$path];
        if (isset($entry['modes']) && $entry['modes'] !== []) {
            return array_keys($entry['modes']);
        }

        /** @var array<string, true> $modes */
        $modes = [];
        foreach ($this->collectAliasTargets($entry['value']) as $target) {
            if (! isset($entries[$target])) {
                continue;
            }

            foreach ($this->effectiveModes($target, $entries, [...$visiting, $path]) as $mode) {
                $modes[$mode] = true;
            }
        }

        return array_keys($modes);
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

        if (\is_string($value) && preg_match('/^\{(.+)}$/', $value, $matches) === 1) {
            return [$matches[1]];
        }

        return [];
    }

    /**
     * Build a typed value object from a resolved base value plus its per-mode
     * resolved values. Mode support covers the DTCG-spec types only; the
     * non-spec extras (boolean, string, link) ignore modes.
     *
     * @param array<string, mixed>|null $modes Resolved raw value per mode
     */
    private function buildValue(?string $type, mixed $value, ?array $modes): TokenValueInterface
    {
        return match ($type) {
            'color' => $this->buildColor($value, $modes),
            'dimension' => $this->buildDimension($value, $modes),
            'duration' => $this->buildDuration($value, $modes),
            'fontFamily' => $this->buildFontFamily($value, $modes),
            'fontWeight' => $this->buildFontWeight($value, $modes),
            'number' => $this->buildNumber($value, $modes),
            'boolean' => new BooleanValue((bool) $value),
            'string' => new StringValue($this->requireScalar($value, 'string')),
            'link' => new LinkValue($this->requireScalar($value, 'link')),
            'cubicBezier' => $this->buildCubicBezier($value, $modes),
            'strokeStyle' => $this->buildStrokeStyle($value, $modes),
            'border' => $this->buildBorder($value, $modes),
            'transition' => $this->buildTransition($value, $modes),
            'gradient' => $this->buildGradient($value, $modes),
            'typography' => $this->buildTypography($value, $modes),
            'shadow' => $this->buildShadow($value, $modes),
            default => throw TokenException::unsupportedType($type),
        };
    }

    /**
     * Build the per-mode sibling map by running each resolved mode value
     * through the same builder as the base value.
     *
     * @template T of TokenValueInterface
     *
     * @param array<string, mixed>|null $modes
     * @param callable(mixed): T       $builder
     *
     * @return array<string, T>|null
     */
    private function buildModeMap(?array $modes, callable $builder): ?array
    {
        if ($modes === null) {
            return null;
        }

        $map = [];
        foreach ($modes as $mode => $raw) {
            $map[$mode] = $builder($raw);
        }

        return $map;
    }

    /**
     * Ensure a value is an array (DTCG object), else throw a descriptive error.
     *
     * @return array<string|int, mixed>
     */
    private function requireArray(mixed $value, string $context): array
    {
        if (! \is_array($value)) {
            throw TokenException::invalidValue(\sprintf('%s expects an object, got %s.', $context, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Ensure a required key is present in a DTCG object, returning its value.
     *
     * @param array<string|int, mixed> $array
     */
    private function requireKey(array $array, string|int $key, string $context): mixed
    {
        if (! \array_key_exists($key, $array)) {
            throw TokenException::invalidValue(\sprintf('%s is missing required key "%s".', $context, (string) $key));
        }

        return $array[$key];
    }

    /**
     * Ensure a value is a scalar and cast it to string, else throw.
     */
    private function requireScalar(mixed $value, string $type): string
    {
        if (! \is_scalar($value)) {
            throw TokenException::invalidValue(\sprintf('%s token expects a scalar value, got %s.', $type, get_debug_type($value)));
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildNumber(mixed $value, ?array $modes = null): NumberValue
    {
        if (! is_numeric($value)) {
            throw TokenException::invalidValue(\sprintf('number token expects a numeric value, got %s.', get_debug_type($value)));
        }

        return new NumberValue((float) $value, $this->buildModeMap($modes, fn (mixed $v): NumberValue => $this->buildNumber($v)));
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildFontFamily(mixed $value, ?array $modes = null): FontFamilyValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): FontFamilyValue => $this->buildFontFamily($v));

        if (\is_string($value)) {
            return new FontFamilyValue([$value], $modeMap);
        }

        if (! \is_array($value)) {
            throw TokenException::invalidValue('FontFamily token value must be a string or array of strings.');
        }

        /** @var list<string> $families */
        $families = [];
        foreach ($value as $family) {
            if (! \is_string($family)) {
                throw TokenException::invalidValue(\sprintf('FontFamily entries must be strings, got %s.', get_debug_type($family)));
            }

            $families[] = $family;
        }

        return new FontFamilyValue($families, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildFontWeight(mixed $value, ?array $modes = null): NumberValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): NumberValue => $this->buildFontWeight($v));

        if (\is_string($value) && ! is_numeric($value)) {
            $lower = strtolower($value);
            if (! isset(self::FONT_WEIGHT_MAP[$lower])) {
                throw TokenException::invalidValue(\sprintf('unknown fontWeight keyword "%s".', $value));
            }

            return new NumberValue((float) self::FONT_WEIGHT_MAP[$lower], $modeMap);
        }

        if (! is_numeric($value)) {
            throw TokenException::invalidValue(\sprintf('fontWeight expects a keyword or number, got %s.', get_debug_type($value)));
        }

        $numeric = (float) $value;
        // CSS font-weight is an integer in the 1..1000 range.
        if ($numeric < 1 || $numeric > 1000 || floor($numeric) !== $numeric) {
            throw TokenException::invalidValue(\sprintf('fontWeight number "%s" must be an integer between 1 and 1000.', $value));
        }

        return new NumberValue($numeric, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildDimension(mixed $value, ?array $modes = null): DimensionValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): DimensionValue => $this->buildDimension($v));

        // legacy: bare number treated as px
        if (is_numeric($value)) {
            return new DimensionValue((float) $value, 'px', $modeMap);
        }

        $value = $this->requireArray($value, 'Dimension token value');

        /** @var int|float $dimValue */
        $dimValue = $this->requireKey($value, 'value', 'Dimension token value');
        $dimUnit = $this->requireUnit($value, self::DIMENSION_UNITS, 'Dimension token value');

        return new DimensionValue((float) $dimValue, $dimUnit, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildDuration(mixed $value, ?array $modes = null): DimensionValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): DimensionValue => $this->buildDuration($v));

        $value = $this->requireArray($value, 'Duration token value');

        /** @var int|float $durValue */
        $durValue = $this->requireKey($value, 'value', 'Duration token value');
        $durUnit = $this->requireUnit($value, self::DURATION_UNITS, 'Duration token value');

        return new DimensionValue((float) $durValue, $durUnit, $modeMap);
    }

    /**
     * Require a `unit` key whose value belongs to the allowed set.
     *
     * @param array<string|int, mixed> $value
     * @param list<string>             $allowed
     */
    private function requireUnit(array $value, array $allowed, string $context): string
    {
        $unit = $this->requireKey($value, 'unit', $context);

        if (! \is_string($unit) || ! \in_array($unit, $allowed, true)) {
            throw TokenException::invalidValue(\sprintf(
                '%s has invalid unit "%s"; expected one of %s.',
                $context,
                \is_string($unit) ? $unit : get_debug_type($unit),
                implode(', ', $allowed),
            ));
        }

        return $unit;
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildColor(mixed $value, ?array $modes = null): ColorValue
    {
        $modeColors = $this->buildModeMap($modes, fn (mixed $v): ColorValue => $this->buildSingleColor($v));

        return $this->buildSingleColor($value, $modeColors);
    }

    /**
     * @param array<string, ColorValue>|null $modes
     */
    private function buildSingleColor(mixed $value, ?array $modes = null): ColorValue
    {
        if (\is_string($value)) {
            return ColorValue::fromHex($value, $modes);
        }

        if (\is_array($value) && isset($value['colorSpace'])) {
            /** @var string $colorSpace */
            $colorSpace = $value['colorSpace'];

            /** @var list<float|null>|null $rawComponents */
            $rawComponents = $value['channels'] ?? $value['components'] ?? null;
            if ($rawComponents === null) {
                throw TokenException::invalidValue('DTCG color must have "channels" or "components".');
            }

            $components = [];
            foreach ($rawComponents as $component) {
                $components[] = $component === null ? null : (float) $component;
            }

            /** @var int|float $alpha */
            $alpha = $value['alpha'] ?? 1.0;
            $hex = isset($value['hex']) && \is_string($value['hex']) ? $value['hex'] : null;

            return ColorValue::fromComponents($colorSpace, $components, (float) $alpha, $hex, $modes);
        }

        throw TokenException::invalidValue('Color token value must be a hex string or DTCG color object.');
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildTypography(mixed $value, ?array $modes = null): TypographyValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): TypographyValue => $this->buildTypography($v));

        $value = $this->requireArray($value, 'Typography token value');

        $fontFamily = $this->buildFontFamily($this->requireKey($value, 'fontFamily', 'Typography token value'));
        $fontSize = $this->buildDimension($this->requireKey($value, 'fontSize', 'Typography token value'));
        $fontWeight = $this->buildFontWeight($this->requireKey($value, 'fontWeight', 'Typography token value'));

        $letterSpacing = isset($value['letterSpacing']) ? $this->buildDimension($value['letterSpacing']) : null;

        $lineHeight = null;
        if (isset($value['lineHeight'])) {
            $lh = $value['lineHeight'];
            if (\is_array($lh) && isset($lh['value'], $lh['unit'])) {
                $lineHeight = $this->buildDimension($lh);
            } else {
                /** @var int|float $lh */
                $lineHeight = new NumberValue((float) $lh);
            }
        }

        // Preserve any sub-properties beyond the five core ones (textTransform,
        // fontStyle, textDecoration, paragraphSpacing, wordSpacing, etc.).
        $core = ['fontFamily', 'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight'];
        /** @var array<string, mixed> $extras */
        $extras = [];
        foreach ($value as $key => $raw) {
            if (! \in_array($key, $core, true)) {
                $extras[(string) $key] = $raw;
            }
        }

        return new TypographyValue($fontFamily, $fontSize, $fontWeight, $letterSpacing, $lineHeight, $extras, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildGradient(mixed $value, ?array $modes = null): GradientValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): GradientValue => $this->buildGradient($v));

        if (! \is_array($value)) {
            throw TokenException::invalidValue('Gradient token value must be an array of color stops.');
        }

        /** @var list<array{color: ColorValue, position: float}> $stops */
        $stops = [];
        foreach ($value as $stop) {
            $stop = $this->requireArray($stop, 'Gradient stop');

            /** @var int|float $position */
            $position = $this->requireKey($stop, 'position', 'Gradient stop');
            if (! is_numeric($position) || $position < 0 || $position > 1) {
                throw TokenException::invalidValue(\sprintf(
                    'Gradient stop position must be a number in [0, 1], got %s.',
                    is_numeric($position) ? (string) $position : get_debug_type($position),
                ));
            }

            $stops[] = [
                'color' => $this->buildSingleColor($this->requireKey($stop, 'color', 'Gradient stop')),
                'position' => (float) $position,
            ];
        }

        return new GradientValue($stops, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildTransition(mixed $value, ?array $modes = null): TransitionValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): TransitionValue => $this->buildTransition($v));

        $value = $this->requireArray($value, 'Transition token value');

        $duration = $this->buildDuration($this->requireKey($value, 'duration', 'Transition token value'));
        $delay = $this->buildDuration($this->requireKey($value, 'delay', 'Transition token value'));
        $timingFunction = $this->buildCubicBezier($this->requireKey($value, 'timingFunction', 'Transition token value'));

        return new TransitionValue($duration, $delay, $timingFunction, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildBorder(mixed $value, ?array $modes = null): BorderValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): BorderValue => $this->buildBorder($v));

        $value = $this->requireArray($value, 'Border token value');

        $color = $this->buildSingleColor($this->requireKey($value, 'color', 'Border token value'));
        $width = $this->buildDimension($this->requireKey($value, 'width', 'Border token value'));
        $style = $this->buildStrokeStyle($this->requireKey($value, 'style', 'Border token value'));

        return new BorderValue($color, $width, $style, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildStrokeStyle(mixed $value, ?array $modes = null): StrokeStyleValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): StrokeStyleValue => $this->buildStrokeStyle($v));

        if (\is_string($value)) {
            return StrokeStyleValue::fromKeyword($value, $modeMap);
        }

        if (\is_array($value)) {
            /** @var list<DimensionValue> $dashArray */
            $dashArray = [];
            $rawDashArray = $value['dashArray'] ?? [];
            if (\is_array($rawDashArray)) {
                foreach ($rawDashArray as $entry) {
                    if (\is_array($entry)) {
                        $dashArray[] = $this->buildDimension($entry);
                    } elseif (is_numeric($entry)) {
                        // Bare number: DTCG implies pixels for dash lengths.
                        $dashArray[] = new DimensionValue((float) $entry, 'px');
                    }
                }
            }

            // lineCap defaults to 'butt' when absent, but a present value must be valid.
            $lineCap = 'butt';
            if (\array_key_exists('lineCap', $value)) {
                $rawLineCap = $value['lineCap'];
                if (! \is_string($rawLineCap) || ! \in_array($rawLineCap, self::LINE_CAPS, true)) {
                    throw TokenException::invalidValue(\sprintf(
                        'Invalid strokeStyle lineCap "%s"; expected one of %s.',
                        \is_string($rawLineCap) ? $rawLineCap : get_debug_type($rawLineCap),
                        implode(', ', self::LINE_CAPS),
                    ));
                }

                $lineCap = $rawLineCap;
            }

            return StrokeStyleValue::fromObject($dashArray, $lineCap, $modeMap);
        }

        throw TokenException::invalidValue('StrokeStyle token value must be a string or object.');
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildCubicBezier(mixed $value, ?array $modes = null): CubicBezierValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): CubicBezierValue => $this->buildCubicBezier($v));

        $value = $this->requireArray($value, 'CubicBezier token value');

        $nums = array_values($value);
        if (\count($nums) < 4) {
            throw TokenException::invalidValue(\sprintf('CubicBezier token value must be an array of 4 numbers, got %d.', \count($nums)));
        }

        $floats = [];
        for ($i = 0; $i < 4; $i++) {
            $num = $nums[$i];
            if (! is_numeric($num)) {
                throw TokenException::invalidValue(\sprintf('CubicBezier token value entry %d must be numeric, got %s.', $i, get_debug_type($num)));
            }

            $floats[] = (float) $num;
        }

        // The x coordinates (control points 0 and 2) must lie in [0, 1]; y is free.
        foreach ([0, 2] as $xIndex) {
            if ($floats[$xIndex] < 0 || $floats[$xIndex] > 1) {
                throw TokenException::invalidValue(\sprintf(
                    'CubicBezier x coordinate at index %d must be in [0, 1], got %s.',
                    $xIndex,
                    $floats[$xIndex],
                ));
            }
        }

        /** @var array{float, float, float, float} $points */
        $points = $floats;

        return new CubicBezierValue($points, $modeMap);
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildShadow(mixed $value, ?array $modes = null): ShadowValue
    {
        if (! \is_array($value)) {
            throw TokenException::invalidValue('Shadow token value must be an object or array of objects.');
        }

        $modeShadows = $this->buildModeMap($modes, fn (mixed $v): ShadowValue => $this->buildShadow($v));

        return new ShadowValue($this->normalizeShadowLayers($value), $modeShadows);
    }

    /**
     * Normalize shadow value: single layer (object) or multi-layer (array of objects).
     *
     * @param array<string|int, mixed> $value
     *
     * @return list<array{offsetX: DimensionValue, offsetY: DimensionValue, blur: DimensionValue, spread: DimensionValue, color: ColorValue, inset: bool}>
     */
    private function normalizeShadowLayers(array $value): array
    {
        if (array_is_list($value)) {
            $layers = [];
            foreach ($value as $layer) {
                if (! \is_array($layer)) {
                    throw TokenException::invalidValue('Shadow layer must be an object.');
                }

                $layers[] = $this->flattenShadowLayer($layer);
            }

            return $layers;
        }

        return [$this->flattenShadowLayer($value)];
    }

    /**
     * Flatten a DTCG shadow layer {offsetX: {value, unit}, ...} into typed
     * dimensions plus color/inset.
     *
     * @param array<int|string, mixed> $layer
     *
     * @return array{offsetX: DimensionValue, offsetY: DimensionValue, blur: DimensionValue, spread: DimensionValue, color: ColorValue, inset: bool}
     */
    private function flattenShadowLayer(array $layer): array
    {
        return [
            'offsetX' => $this->shadowDimension($layer, 'offsetX', required: true),
            'offsetY' => $this->shadowDimension($layer, 'offsetY', required: true),
            // blur and spread are optional per DTCG; default to 0 when absent.
            'blur' => $this->shadowDimension($layer, 'blur', required: false),
            'spread' => $this->shadowDimension($layer, 'spread', required: false),
            'color' => $this->buildSingleColor($this->requireKey($layer, 'color', 'Shadow layer')),
            'inset' => (bool) ($layer['inset'] ?? false),
        ];
    }

    /**
     * Resolve a shadow dimension key {value, unit} into a DimensionValue,
     * preserving the authored unit. Optional keys default to 0px when absent.
     *
     * @param array<int|string, mixed> $layer
     */
    private function shadowDimension(array $layer, string $key, bool $required): DimensionValue
    {
        if (! $required && ! \array_key_exists($key, $layer)) {
            return new DimensionValue(0.0, 'px');
        }

        $dimension = $this->requireArray($this->requireKey($layer, $key, 'Shadow layer'), \sprintf('Shadow layer "%s"', $key));

        $component = $this->requireKey($dimension, 'value', \sprintf('Shadow layer "%s"', $key));
        if (! is_numeric($component)) {
            throw TokenException::invalidValue(\sprintf('Shadow layer "%s" value must be numeric, got %s.', $key, get_debug_type($component)));
        }

        $unit = $this->requireUnit($dimension, self::DIMENSION_UNITS, \sprintf('Shadow layer "%s"', $key));

        return new DimensionValue((float) $component, $unit);
    }
}
