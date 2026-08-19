<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Parser;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Str;
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
 * Mode keys are int|string: PHP canonicalizes numeric JSON keys ("2024") to
 * ints; they are normalized back to strings by effectiveModes().
 *
 * @phpstan-type RawEntry array{type: string|null, value: mixed, modes?: array<int|string, mixed>, description: string|null, deprecated: bool}
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
     * Unit applied when DTCG implies pixels: legacy bare-number dimensions,
     * bare-number dash lengths, and omitted shadow blur/spread.
     */
    private const string DEFAULT_DIMENSION_UNIT = 'px';

    /**
     * Parse a raw DTCG token tree into typed value objects plus metadata.
     *
     * @param array<string, mixed> $raw
     */
    public function parse(array $raw): ParseResult
    {
        // Step 1: Flatten tree into raw entries with inherited $type and $deprecated
        // array-key, not string: PHP canonicalizes a numeric token path
        // ("4", "2024") to an int array key.
        /** @var array<array-key, RawEntry> $entries */
        $entries = [];
        /** @var array<array-key, true> $groupPaths */
        $groupPaths = [];
        $this->walkTree($raw, '', null, false, $entries, $groupPaths);

        // Step 2: Resolve each token in every mode it (transitively) supports,
        // then build typed value objects carrying their per-mode siblings.
        $resolver = new AliasResolver($entries);

        /** @var array<string, TokenValueInterface> $tokens */
        $tokens = [];
        /** @var array<string, TokenMetadata> $metadata */
        $metadata = [];
        foreach ($entries as $path => $entry) {
            // PHP canonicalizes numeric keys ("4", "2024") to ints; paths are
            // strings everywhere downstream, including the cache payload.
            $path = (string) $path;

            try {
                $base = $resolver->resolve($path, null);

                $modeValues = null;
                $modeSet = $resolver->effectiveModes($path);
                if ($modeSet !== []) {
                    $modeValues = [];
                    foreach ($modeSet as $mode) {
                        $modeValues[$mode] = $resolver->resolve($path, $mode);
                    }
                }

                $tokens[$path] = $this->buildValue($entry['type'], $base, $modeValues);
                $metadata[$path] = new TokenMetadata($entry['description'], $entry['deprecated'], $modeSet, $entry['type']);
            } catch (TokenException $exception) {
                // On a large file, a type-scoped message without the token
                // path is close to useless.
                throw TokenException::inToken($path, $exception);
            }
        }

        return new ParseResult($tokens, $metadata);
    }

    /**
     * Walk the JSON tree, collecting token entries with inherited $type and $deprecated.
     *
     * @param array<string, mixed> $node
     * @param array<array-key, RawEntry> $entries
     * @param array<array-key, true> $groupPaths
     */
    private function walkTree(array $node, string $prefix, ?string $inheritedType, bool $inheritedDeprecated, array &$entries, array &$groupPaths): void
    {
        try {
            $groupType = $this->stringTypeOf($node) ?? $inheritedType;
        } catch (TokenException $exception) {
            // The per-token wrapping in parse() runs after the walk; a bare
            // "$type must be a string" from deep inside a large file gives no
            // clue where to look.
            throw $prefix === '' ? $exception : TokenException::inGroup($prefix, $exception);
        }

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
                if (isset($entries[$path])) {
                    throw TokenException::duplicatePath($path);
                }

                if (isset($groupPaths[$path])) {
                    throw TokenException::pathIsTokenAndGroup($path);
                }

                foreach ($child as $childKey => $grandChild) {
                    // Deep-merging a token file with a group file yields a node
                    // with both $value and children; treating it as a token
                    // would silently drop every nested token. Non-array junk
                    // keys stay tolerated, as everywhere else in the walk.
                    if (\is_array($grandChild) && ! str_starts_with((string) $childKey, '$')) {
                        throw TokenException::pathIsTokenAndGroup($path);
                    }
                }

                $description = $child['$description'] ?? null;

                try {
                    /** @var RawEntry $entry */
                    $entry = [
                        'type' => $this->stringTypeOf($child) ?? $groupType,
                        'value' => $child['$value'],
                        'description' => \is_string($description) ? $description : null,
                        'deprecated' => \array_key_exists('$deprecated', $child)
                            ? $this->normalizeDeprecated($child['$deprecated'])
                            : $groupDeprecated,
                    ];

                    // array_key_exists, not isset: an authored `"mode": null` is
                    // present-but-invalid, and must reach the type check below
                    // rather than silently disabling modes.
                    if (isset($child['$extensions']) && \is_array($child['$extensions']) && \array_key_exists('mode', $child['$extensions'])) {
                        $mode = $child['$extensions']['mode'];
                        if (! \is_array($mode)) {
                            throw TokenException::invalidValue(\sprintf(
                                '$extensions.mode must be an object mapping mode names to values, got %s.',
                                get_debug_type($mode),
                            ));
                        }

                        $entry['modes'] = $mode;
                    }
                } catch (TokenException $exception) {
                    throw TokenException::inToken($path, $exception);
                }

                $entries[$path] = $entry;
            } else {
                if (isset($entries[$path])) {
                    throw TokenException::pathIsTokenAndGroup($path);
                }

                $groupPaths[$path] = true;
                $this->walkTree($child, $path, $groupType, $groupDeprecated, $entries, $groupPaths);
            }
        }
    }

    /**
     * Read a node's `$type`, requiring it to be a string when present.
     *
     * @param array<string|int, mixed> $node
     */
    private function stringTypeOf(array $node): ?string
    {
        // Key presence, not ??: an authored `"$type": null` is malformed, and
        // must not be treated as "absent" (which would inherit the group's).
        if (! \array_key_exists('$type', $node)) {
            return null;
        }

        $type = $node['$type'];
        if (! \is_string($type)) {
            throw TokenException::invalidValue(\sprintf('$type must be a string, got %s.', get_debug_type($type)));
        }

        return $type;
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
     * Build a typed value object from a resolved base value plus its per-mode
     * resolved values. Every token type is mode-aware, including the non-spec
     * extras (boolean, string, link).
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
            'boolean' => $this->buildBoolean($value, $modes),
            'string' => $this->buildString($value, $modes),
            'link' => $this->buildLink($value, $modes),
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
     * Ensure a value is a finite number and cast it to float, else throw.
     */
    private function requireNumeric(mixed $value, string $context): float
    {
        if (! is_numeric($value)) {
            throw TokenException::invalidValue(\sprintf('%s must be numeric, got %s.', $context, get_debug_type($value)));
        }

        $float = (float) $value;
        if (! is_finite($float)) {
            // is_numeric("1e999") is true but overflows to INF, which would
            // serialize as "inf" in CSS output.
            throw TokenException::invalidValue(\sprintf('%s must be a finite number.', $context));
        }

        return $float;
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildBoolean(mixed $value, ?array $modes = null): BooleanValue
    {
        if (! \is_bool($value)) {
            throw TokenException::invalidValue(\sprintf('boolean token expects a bool value, got %s.', get_debug_type($value)));
        }

        return new BooleanValue($value, $this->buildModeMap($modes, fn (mixed $v): BooleanValue => $this->buildBoolean($v)));
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildString(mixed $value, ?array $modes = null): StringValue
    {
        return new StringValue(
            $this->requireScalar($value, 'string'),
            $this->buildModeMap($modes, fn (mixed $v): StringValue => $this->buildString($v)),
        );
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildLink(mixed $value, ?array $modes = null): LinkValue
    {
        return new LinkValue(
            $this->requireScalar($value, 'link'),
            $this->buildModeMap($modes, fn (mixed $v): LinkValue => $this->buildLink($v)),
        );
    }

    /**
     * @param array<string, mixed>|null $modes
     */
    private function buildNumber(mixed $value, ?array $modes = null): NumberValue
    {
        return new NumberValue(
            $this->requireNumeric($value, 'number token value'),
            $this->buildModeMap($modes, fn (mixed $v): NumberValue => $this->buildNumber($v)),
        );
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
                throw TokenException::invalidValue(\sprintf('unknown fontWeight keyword "%s".', Str::excerpt($value)));
            }

            return new NumberValue((float) self::FONT_WEIGHT_MAP[$lower], $modeMap);
        }

        if (! is_numeric($value)) {
            throw TokenException::invalidValue(\sprintf('fontWeight expects a keyword or number, got %s.', get_debug_type($value)));
        }

        $numeric = (float) $value;
        // CSS font-weight is an integer in the 1..1000 range.
        if ($numeric < 1 || $numeric > 1000 || floor($numeric) !== $numeric) {
            throw TokenException::invalidValue(\sprintf('fontWeight number "%s" must be an integer between 1 and 1000.', Str::excerpt((string) $value)));
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
            return new DimensionValue(
                $this->requireNumeric($value, 'Dimension token value'),
                self::DEFAULT_DIMENSION_UNIT,
                $modeMap,
            );
        }

        $value = $this->requireArray($value, 'Dimension token value');

        $dimValue = $this->requireNumeric(
            $this->requireKey($value, 'value', 'Dimension token value'),
            'Dimension token value "value"',
        );
        $dimUnit = $this->requireUnit($value, self::DIMENSION_UNITS, 'Dimension token value');

        return new DimensionValue($dimValue, $dimUnit, $modeMap);
    }

    /**
     * Durations are deliberately carried by {@see DimensionValue} with an
     * `ms`/`s` unit: the serialization ("200ms") and the value/unit surface
     * are identical to dimensions, so a dedicated class would only duplicate
     * it. The unit set is what distinguishes the two at parse time.
     *
     * @param array<string, mixed>|null $modes
     */
    private function buildDuration(mixed $value, ?array $modes = null): DimensionValue
    {
        $modeMap = $this->buildModeMap($modes, fn (mixed $v): DimensionValue => $this->buildDuration($v));

        $value = $this->requireArray($value, 'Duration token value');

        $durValue = $this->requireNumeric(
            $this->requireKey($value, 'value', 'Duration token value'),
            'Duration token value "value"',
        );
        $durUnit = $this->requireUnit($value, self::DURATION_UNITS, 'Duration token value');

        return new DimensionValue($durValue, $durUnit, $modeMap);
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
                \is_string($unit) ? Str::excerpt($unit) : get_debug_type($unit),
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
            $colorSpace = $value['colorSpace'];
            if (! \is_string($colorSpace)) {
                throw TokenException::invalidValue(\sprintf(
                    'DTCG color "colorSpace" must be a string, got %s.',
                    get_debug_type($colorSpace),
                ));
            }

            if (isset($value['hex']) && ! \is_string($value['hex'])) {
                throw TokenException::invalidValue(\sprintf(
                    'DTCG color "hex" must be a string, got %s.',
                    get_debug_type($value['hex']),
                ));
            }

            $rawComponents = $value['channels'] ?? $value['components'] ?? null;
            if ($rawComponents === null) {
                throw TokenException::invalidValue('DTCG color must have "channels" or "components".');
            }

            if (! \is_array($rawComponents)) {
                throw TokenException::invalidValue(\sprintf(
                    'DTCG color channels must be an array of numbers, got %s.',
                    get_debug_type($rawComponents),
                ));
            }

            $components = [];
            foreach ($rawComponents as $component) {
                if ($component !== null && ! is_numeric($component)) {
                    throw TokenException::invalidValue(\sprintf(
                        'DTCG color channel must be numeric or null, got %s.',
                        get_debug_type($component),
                    ));
                }

                $components[] = $component === null
                    ? null
                    : $this->requireNumeric($component, 'DTCG color channel');
            }

            $alpha = $this->requireNumeric($value['alpha'] ?? 1.0, 'DTCG color "alpha"');
            $hex = isset($value['hex']) && \is_string($value['hex']) ? $value['hex'] : null;

            return ColorValue::fromComponents($colorSpace, $components, $alpha, $hex, $modes);
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
                $lineHeight = new NumberValue($this->requireNumeric($lh, 'Typography lineHeight'));
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

        // Named arguments: params 4 and 5 have overlapping nullable types, so
        // a positional swap would type-check silently.
        return new TypographyValue(
            fontFamilyValue: $fontFamily,
            fontSizeValue: $fontSize,
            fontWeightValue: $fontWeight,
            letterSpacingValue: $letterSpacing,
            lineHeightValue: $lineHeight,
            extras: $extras,
            modes: $modeMap,
        );
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
                    is_numeric($position) ? Str::excerpt((string) $position) : get_debug_type($position),
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
            if (! \is_array($rawDashArray)) {
                throw TokenException::invalidValue(\sprintf(
                    'StrokeStyle dashArray must be an array of dimensions, got %s.',
                    get_debug_type($rawDashArray),
                ));
            }

            foreach ($rawDashArray as $entry) {
                if (\is_array($entry)) {
                    $dashArray[] = $this->buildDimension($entry);
                } elseif (is_numeric($entry)) {
                    // Bare number: DTCG implies pixels for dash lengths.
                    $dashArray[] = new DimensionValue(
                        $this->requireNumeric($entry, 'StrokeStyle dashArray entry'),
                        self::DEFAULT_DIMENSION_UNIT,
                    );
                } else {
                    // Dropping it silently would render a dashed token solid.
                    throw TokenException::invalidValue(\sprintf(
                        'StrokeStyle dashArray entry must be a dimension object or a number, got %s.',
                        get_debug_type($entry),
                    ));
                }
            }

            // lineCap defaults to 'butt' when absent, but a present value must be valid.
            $lineCap = 'butt';
            if (\array_key_exists('lineCap', $value)) {
                $rawLineCap = $value['lineCap'];
                if (! \is_string($rawLineCap) || ! \in_array($rawLineCap, self::LINE_CAPS, true)) {
                    throw TokenException::invalidValue(\sprintf(
                        'Invalid strokeStyle lineCap "%s"; expected one of %s.',
                        \is_string($rawLineCap) ? Str::excerpt($rawLineCap) : get_debug_type($rawLineCap),
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
            $floats[] = $this->requireNumeric($nums[$i], \sprintf('CubicBezier token value entry %d', $i));
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
            return new DimensionValue(0.0, self::DEFAULT_DIMENSION_UNIT);
        }

        $dimension = $this->requireArray($this->requireKey($layer, $key, 'Shadow layer'), \sprintf('Shadow layer "%s"', $key));

        $component = $this->requireNumeric(
            $this->requireKey($dimension, 'value', \sprintf('Shadow layer "%s"', $key)),
            \sprintf('Shadow layer "%s" value', $key),
        );
        $unit = $this->requireUnit($dimension, self::DIMENSION_UNITS, \sprintf('Shadow layer "%s"', $key));

        return new DimensionValue($component, $unit);
    }
}
