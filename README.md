# n5s/dtcg-tokens

Read, resolve, and render [DTCG design tokens](https://tr.designtokens.org/) at runtime in PHP. It implements the [Design Tokens Community Group specification](https://tr.designtokens.org/) and is PHP-first, with optional [Twig](#twig) and [Symfony](#symfony) bridges.

![PHP ^8.4](https://img.shields.io/badge/PHP-%5E8.4-777BB4)
![License MIT](https://img.shields.io/badge/License-MIT-green)

## Install

```bash
composer require n5s/dtcg-tokens
```

Requires **PHP 8.4** or newer.

## Quick start (plain PHP)

```php
use n5s\DtcgTokens\Tokens;

$tokens = Tokens::fromFile('tokens.json');

$tokens->get('color.primary');         // ColorValue (Stringable)
(string) $tokens->get('space.md');     // "16px"
$tokens->get('color.primary', 'dark'); // mode-aware lookup
```

`Tokens` is an immutable, iterable (`IteratorAggregate`) and countable collection keyed by dot-separated token path. Build it from one or many files, or from an already-decoded array:

```php
Tokens::fromFile('tokens.json');                       // single file
Tokens::fromFiles(['base.json', 'overrides.json']);    // merged, later files win
Tokens::fromArray(['color' => ['$type' => 'color', /* ... */]]);
```

Other methods: `has(string $path): bool`, `all(): array<string, TokenValueInterface>`, and `get(string $path, ?string $mode = null)`. Unknown paths throw a `TokenException`. Per-token metadata is available via `metadata(string $path): ?TokenMetadata` and `allMetadata()` (carrying `$description` and `$deprecated`).

## Supported token types

The parser handles the following DTCG `$type` values:

| Type          | Value object        | Notes                                            |
| ------------- | ------------------- | ------------------------------------------------ |
| `color`       | `ColorValue`        | hex + any CSS Color 4 space, stored losslessly   |
| `dimension`   | `DimensionValue`    | `{ value, unit }`, unit `px` / `rem` / `em`      |
| `duration`    | `DimensionValue`    | `{ value, unit }`, unit `ms` / `s`               |
| `number`      | `NumberValue`       |                                                  |
| `fontFamily`  | `FontFamilyValue`   | string or list of strings                        |
| `fontWeight`  | `NumberValue`       | keyword (`bold`, `medium`, …) mapped to numeric  |
| `cubicBezier` | `CubicBezierValue`  | array of 4 numbers                               |
| `boolean`     | `BooleanValue`      |                                                  |
| `string`      | `StringValue`       |                                                  |
| `link`        | `LinkValue`         |                                                  |
| `strokeStyle` | `StrokeStyleValue`  | keyword or `{ dashArray, lineCap }`              |
| `border`      | `BorderValue`       | composite: color + width + style                 |
| `shadow`      | `ShadowValue`       | single or multi-layer, `inset` supported         |
| `gradient`    | `GradientValue`     | list of `{ color, position }` stops              |
| `transition`  | `TransitionValue`   | duration + delay + timing function               |
| `typography`  | `TypographyValue`   | composite font shorthand; extra props preserved  |

### Color spaces

`ColorValue` accepts hex strings (`#rrggbb` and `#rrggbbaa`) and DTCG color objects. Colors are stored **losslessly** in their authored color space — the `components` (with `null` representing a CSS `none` / powerless channel) and `alpha` are kept verbatim, never squashed to sRGB at parse time.

The accepted color spaces match the CSS Color 4 / terrazzo set: `srgb`, `srgb-linear`, `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020`, `lab`, `lab-d65`, `lch`, `oklab`, `oklch`, `okhsv`, `hsl`, `hwb`, `xyz`, `xyz-d50`, `xyz-d65`. An unknown space throws.

`fontWeight` keywords (`thin`, `light`, `regular`, `medium`, `semi-bold`, `bold`, `black`, …) are mapped to their numeric equivalents; an unknown keyword, or a numeric weight outside `1`–`1000`, throws a `TokenException`.

#### CSS serialization (`toCss()` / `(string)`)

Casting to string emits faithful CSS Color 4 — no gamut conversion:

| Color space(s)                                                            | Output                              |
| ------------------------------------------------------------------------- | ----------------------------------- |
| `srgb`, hex                                                               | `rgb(R G B)` / `rgb(R G B / A)`     |
| `hsl`                                                                     | `hsl(H S% L%)`                      |
| `hwb`                                                                     | `hwb(H W% B%)`                      |
| `lab`, `lab-d65`                                                          | `lab(L a b)`                        |
| `lch`                                                                     | `lch(L C H)`                        |
| `oklab`                                                                   | `oklab(L a b)`                      |
| `oklch`                                                                   | `oklch(L C H)`                      |
| `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020`, `srgb-linear`, `xyz`, `xyz-d50`, `xyz-d65` | `color(<space> c1 c2 c3)` |
| `okhsv`                                                                   | hex fallback (no CSS function)      |

A `null` component renders as `none`; alpha below `1` is appended as ` / A` inside the function. `okhsv` has no CSS function, so `toCss()` emits the author-provided `hex` fallback if present, otherwise it throws.

#### Conversion to sRGB (`toHex()` / `toRgb()`)

These reduce a color to 8-bit sRGB:

- `srgb`, `hsl`, `oklch` (and hex): computed directly.
- Any other space: uses the author-provided `hex` fallback if present, else **throws** — wide-gamut values are never silently approximated.

For example, `display-p3 [1 0 0]` serializes to `color(display-p3 1 0 0)`; `toHex()` throws unless the token also carries a `"hex"` fallback (e.g. `"#fd0000"`), in which case that value is returned verbatim.

## Value objects

Every token resolves to an immutable value object implementing `TokenValueInterface extends \Stringable`. Casting to string produces a CSS-ready representation, so value objects can be dropped straight into templates or stylesheets.

Color tokens additionally expose `toHex()` and `toRgb(?float $alpha = null)`:

```php
$color = $tokens->get('color.primary');

(string) $color;        // "rgb(255 0 0)"  — default string form is rgb()
$color->toHex();        // "#ff0000"
$color->toRgb(0.5);     // "rgb(255 0 0 / 0.5)"
```

## Theming / modes

Mode-specific values are declared under `$extensions.mode.<name>`:

```json
{
  "color": {
    "$type": "color",
    "fg": {
      "$value": "#ffffff",
      "$extensions": { "mode": { "dark": "#000000" } }
    }
  }
}
```

Resolve a mode by passing it to `get()`, or get a mode-bound value object via `forMode()`:

```php
$tokens->get('color.fg');          // base   -> rgb(255 255 255)
$tokens->get('color.fg', 'dark');  // dark   -> rgb(0 0 0)

$tokens->get('color.fg')->forMode('dark');   // same as above
$tokens->get('color.fg')->forMode('nope');   // falls back to the base value
```

An unknown mode falls back to the base value rather than throwing.

Modes work for every standard DTCG token type — `color`, `dimension`, `number`, `fontFamily`, `fontWeight`, `duration`, `cubicBezier`, `strokeStyle`, `border`, `transition`, `shadow`, `gradient`, and `typography`. (The non-spec `boolean`, `string`, and `link` extras are not mode-aware and always return their base value.)

### Aliases and modes

Aliases are resolved per mode: an alias resolves to its target's value **in the same mode**, falling back to the target's base when the target does not declare that mode. A token that aliases a themed token but declares no modes of its own **hoists** the target's modes:

```json
{
  "color": {
    "$type": "color",
    "blue":   { "$value": "#0000ff", "$extensions": { "mode": { "dark": "#000088" } } },
    "accent": { "$value": "{color.blue}" }
  }
}
```

```php
$tokens->get('color.accent');         // -> rgb(0 0 255)   (blue, base)
$tokens->get('color.accent', 'dark'); // -> rgb(0 0 136)   (blue, dark) — hoisted through the alias
```

Hoisting also works through composites (e.g. a `border` whose `color` aliases a themed color becomes mode-aware on that channel) and transitively along alias chains. When a token declares its own modes, those take precedence and no extra modes are hoisted. This mirrors [Terrazzo](https://github.com/terrazzoapp/terrazzo)'s mode resolution.

## Twig

The `TokenExtension` exposes a `token()` function plus `hex` and `rgb` filters.

In a Symfony app it is registered automatically (see [Symfony](#symfony)). For plain Twig, wire it as an attribute extension with a runtime loader:

```php
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Twig\TokenExtension;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

$tokens = Tokens::fromFile('tokens.json');

$twig = new Environment($loader);
$twig->addExtension(new AttributeExtension(TokenExtension::class));
$twig->addRuntimeLoader(new FactoryRuntimeLoader([
    TokenExtension::class => static fn (): TokenExtension => new TokenExtension($tokens),
]));
```

Then in templates:

```twig
{{ token('color.primary')|hex }}      {# #ff0000 #}
{{ token('color.primary')|rgb }}      {# rgb(255 0 0) #}
{{ token('color.primary')|rgb(0.5) }} {# rgb(255 0 0 / 0.5) #}
{{ token('space.md') }}               {# 16px — Stringable, no filter needed #}
{{ token('color.fg', 'dark')|hex }}   {# mode-aware lookup #}
```

The `hex` and `rgb` filters only accept color tokens and throw a `LogicException` otherwise.

## Symfony

Enable the bundle:

```php
// config/bundles.php
return [
    // ...
    n5s\DtcgTokens\Bridge\Symfony\DtcgTokensBundle::class => ['all' => true],
];
```

Configure it under the `dtcg_tokens` key:

```yaml
# config/packages/dtcg_tokens.yaml
dtcg_tokens:
    files:
        - '%kernel.project_dir%/assets/tokens/tokens.json'
    cache: cache.app   # optional PSR-6 pool service id
```

`files` is required (at least one entry); multiple files are merged in order. `cache` is optional — point it at any PSR-6 cache pool service to cache the parsed token tree.

Then inject `n5s\DtcgTokens\Tokens` into any service or controller:

```php
use n5s\DtcgTokens\Tokens;

final class ThemeController
{
    public function __construct(private Tokens $tokens) {}
}
```

The Twig `token()` function and `hex` / `rgb` filters are registered automatically when Twig is installed.

## CSS export

`CssExporter` renders a `Tokens` collection as CSS custom properties:

```php
use n5s\DtcgTokens\Export\CssExporter;

echo new CssExporter()->export($tokens);
```

```css
:root {
  --color-primary: rgb(255 0 0);
  --space-md: 16px;
}
```

The constructor takes an optional `prefix` and `selector`:

```php
new CssExporter(prefix: 'ds', selector: '.theme-dark')->export($tokens);
// .theme-dark { --ds-color-primary: rgb(0 0 0); ... }
```

Token paths are slugged by replacing `.` and spaces with `-`; values use each value object's string form.

## Caching

`CachedTokenFactory` wraps a loader and an optional PSR-6 pool. It parses tokens once, caches the result, and — in debug mode — invalidates the cache when any source file's mtime changes:

```php
use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\JsonFileLoader;

$factory = new CachedTokenFactory(
    loader: JsonFileLoader::fromPaths(['tokens.json']),
    cache: $psr6Pool,   // any Psr\Cache\CacheItemPoolInterface, or null
    debug: $isDebug,
);

$tokens = $factory->create();
```

Without a cache pool it simply parses on first `create()` and reuses the result in-process. The Symfony bundle wires this factory for you.

## Development

```bash
composer qa   # PHPStan (max), ECS, Rector, PHPUnit
```

Built and tested against PHP 8.4. PHPStan runs at max level with strict rules; ECS and Rector enforce style and modernization.

## Limitations

This is a runtime library. It deliberately leaves some things out:

| Not included                 | Notes                                                                                                                                                                                              |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Build-time codegen / plugins | Runtime only — for a full token toolchain with codegen and plugins, see [Terrazzo](https://terrazzo.app)                                                                                            |
| Resolver-document format     |                                                                                                                                                                                                    |
| Alias-graph metadata         | Aliases are resolved in place (including per mode); the reference graph itself is not exposed as data                                                                                               |
| Gamut-conversion math        | Colors serialize faithfully to CSS Color 4; reducing a wide-gamut space to sRGB (`toHex()` / `toRgb()`) needs an sRGB-reducible space (`srgb`, `hsl`, `oklch`) or an author-provided `hex` fallback |

## Credits

Feature scope was informed by [Terrazzo](https://github.com/terrazzoapp/terrazzo), the state-of-the-art JavaScript reference for DTCG tooling.

## License

MIT.
