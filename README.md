# n5s/dtcg-tokens

Read, resolve, and render [DTCG design tokens](https://tr.designtokens.org/) at runtime in PHP. It implements the [Design Tokens Community Group specification](https://tr.designtokens.org/) and is PHP-first, with optional [Twig](#twig) and [Symfony](#symfony) bridges.

[![QA](https://github.com/nlemoine/dtcg-tokens/actions/workflows/qa.yml/badge.svg)](https://github.com/nlemoine/dtcg-tokens/actions/workflows/qa.yml)
[![codecov](https://codecov.io/gh/nlemoine/dtcg-tokens/graph/badge.svg)](https://codecov.io/gh/nlemoine/dtcg-tokens)
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
Tokens::fromLoader($myLoader);                         // any TokenLoaderInterface (HTTP, DB, ...)
```

Other methods: `has(string $path): bool`, `all(): array<string, TokenValueInterface>`, `get(string $path, ?string $mode = null)`, `modes(): list<string>`, and `forMode(string $mode): Tokens`. Per-token metadata is available via `metadata(string $path): ?TokenMetadata` and `allMetadata()` (carrying `$description`, `$deprecated` and `$modes`).

Every failure is a `TokenException`; catch a subtype for differentiated handling: `TokenNotFoundException` (unknown path — consumer-side), `TokenFileException` (unreadable/invalid source file — deployment-side), `TokenParseException` (invalid token content — fix the token file).

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

`ColorValue` accepts hex strings (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa` — anything else, including named colors, throws a `TokenException`) and DTCG color objects. Colors are stored **losslessly** in their authored color space — the `components` (with `null` representing a CSS `none` / powerless channel) and `alpha` are kept verbatim, never squashed to sRGB at parse time.

The accepted color spaces match the CSS Color 4 / terrazzo set: `srgb`, `srgb-linear`, `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020`, `lab`, `lab-d65`, `lch`, `oklab`, `oklch`, `okhsv`, `hsl`, `hwb`, `xyz`, `xyz-d50`, `xyz-d65`. An unknown space throws.

Component ranges follow the [DTCG Color module](https://www.designtokens.org/TR/drafts/color/) (CSS Color 4 conventions):

| Color space(s)                          | Components                                        |
| --------------------------------------- | ------------------------------------------------- |
| `srgb`, `srgb-linear`, `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020` | channels `0`–`1`  |
| `hsl`                                   | H `0`–`360`, S `0`–`100`, L `0`–`100`             |
| `hwb`                                   | H `0`–`360`, W `0`–`100`, B `0`–`100`             |
| `lab`, `lab-d65`, `lch`                 | L `0`–`100`                                       |
| `oklab`, `oklch`                        | L `0`–`1`                                         |

Components are stored verbatim (no parse-time validation of ranges); out-of-range values are clamped — and hue wrapped into `[0, 360)` — only when reducing to 8-bit sRGB via `toHex()` / `toRgb()`, mirroring how CSS clamps at render time. A color that the sRGB converter cannot handle throws a `TokenException`.

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

A `null` component renders as `none`; alpha below `1` is appended as ` / A` inside the function. `okhsv` has no CSS function, so a `hex` fallback is **required at parse time** (a missing one throws immediately, not mid-render) and `toCss()` emits it.

#### Conversion to sRGB (`toHex()` / `toRgb()`)

These reduce a color to 8-bit sRGB:

- `srgb`, `hsl`, `oklch` (and hex): computed directly.
- Any other space: uses the author-provided `hex` fallback if present, else **throws** — wide-gamut values are never silently approximated.

For example, `display-p3 [1 0 0]` serializes to `color(display-p3 1 0 0)`; `toHex()` throws unless the token also carries a `"hex"` fallback (e.g. `"#fd0000"`), in which case that value is returned verbatim.

## Value objects

Every token resolves to an immutable value object implementing `TokenValueInterface extends \Stringable`. Casting to string — or calling `toCss()`, they are interchangeable — produces a CSS-ready representation, so value objects can be dropped straight into templates or stylesheets.

Composite values expose their components: `BorderValue` (`color()`, `width()`, `style()`), `TransitionValue` (`duration()`, `delay()`, `timingFunction()`), `GradientValue` (`stops()`), `ShadowValue` (`layers()`), `TypographyValue` (`fontFamily()`, `fontSize()`, …), `CubicBezierValue` (`points()`), `FontFamilyValue` (`families()`).

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

Modes work for **every** token type, including the non-spec `boolean`, `string`, and `link` extras (a per-theme logo URL is a `link` with a `dark` mode).

Two collection-level helpers cover enumeration and themed rendering:

```php
$tokens->modes();          // ["dark", "dense"] — union of every token's modes
$dark = $tokens->forMode('dark');   // a new collection, every token bound to dark
```

Per-token mode names are also on the metadata: `$tokens->metadata('color.fg')->modes`.

One rule to know: mode-bound values are **mode-terminal** — a value returned by `forMode()` (or a component accessor of a composite) carries no sibling map, so calling `forMode()` on it again returns itself. Always bind modes from the base value, or use `Tokens::forMode()` on the collection.

Modes are resolved eagerly at parse time: every token is built once per mode it (transitively) supports, so parsing costs O(tokens × modes). That is deliberate — lookups and `forMode()` projections are then plain array reads, and the parsed result is what gets cached. With very large files and many modes, put the cost behind `CachedTokenFactory` (see [Caching](#caching)) so it is paid once per change, not per request.

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

The `hex` and `rgb` filters only accept color tokens and throw a `TokenException` otherwise.

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
    ttl: 86400         # optional, seconds; recommended when the pool survives deploys
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

The constructor takes an optional `prefix` and `selector`. Themed output combines a selector with `Tokens::forMode()`:

```php
new CssExporter()->export($tokens);
new CssExporter(selector: '[data-theme="dark"]')->export($tokens->forMode('dark'));
// [data-theme="dark"] { --color-fg: rgb(0 0 0); ... }
```

Token paths are slugged by replacing `.` and spaces with `-`; values use each value object's string form.

Names and values are emitted verbatim, so the exporter refuses anything able to break out of its declaration: a token path that does not slug to a valid custom property name, or a serialized value containing `;`, `{`, `}` or `/*`, throws a `TokenException` (no silent escaping — that would create colliding names and altered values). The constructor holds `prefix` and `selector` to the same standard, so a bad one fails at construction rather than as broken CSS. Token files are treated as trusted developer input; if yours come from an external source (CMS, user upload, third-party export), validate them upstream.

## Caching

`CachedTokenFactory` wraps a loader and an optional PSR-6 pool. It parses tokens once, caches the result, and — in debug mode — invalidates the cache when any source file's mtime changes:

```php
use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\JsonFileLoader;

$factory = new CachedTokenFactory(
    loader: JsonFileLoader::fromPaths(['tokens.json']),
    cache: $psr6Pool,   // any Psr\Cache\CacheItemPoolInterface, or null
    debug: $isDebug,
    ttl: 86_400,        // optional; null = entries live until evicted
);

$tokens = $factory->create();
```

With `debug: false` the pool entry is served without any freshness check, so a pool that **survives deploys** (Redis, APCu) keeps serving the previous release's tokens. Either set a `ttl`, or clear the entry on deploy — its key is exposed as `$factory->cacheKey()`. A pool failure is never fatal: the factory falls back to a fresh parse.

Long-running workers (FrankenPHP, Swoole, RoadRunner) add one caveat: `JsonFileLoader` reads `filemtime()` and `realpath()`, both of which PHP caches per process (the realpath cache holds symlink resolution for up to `realpath_cache_ttl`, 120s by default). Inside a worker, a symlink-swapped deploy can therefore keep resolving into the previous release, and debug-mode mtime checks can briefly serve stale answers. Restarting workers on deploy — which such runtimes need anyway for the code itself — clears both caches.

Without a cache pool it simply parses on first `create()` and reuses the result in-process. The Symfony bundle wires this factory for you.

The factory accepts any `CacheableTokenLoaderInterface` — a `TokenLoaderInterface` extended with `revision()` (an opaque freshness marker: an mtime, an ETag, a content hash; `null` = unknown = always stale in debug) and `fingerprint()` — so a custom loader (HTTP, database, …) keeps caching support. `JsonFileLoader` implements it. Cache keys carry a format version segment, so entries written by an older release miss instead of unserializing into changed value-object classes.

## Development

```bash
composer qa          # PHPStan (max), ECS, Rector, PHPUnit
composer infection   # mutation testing (Infection, needs Xdebug or pcov)
```

Built and tested against PHP 8.4. PHPStan runs at max level with strict rules; ECS and Rector enforce style and modernization; [Infection](https://infection.github.io/) guards test strength with a minimum MSI.

## Upgrading from 1.x

2.0 makes the parser strict. Most changes surface as a `TokenException` on
input that 1.x accepted, so they are loud. **Two are silent — check these
first:**

| Token | 1.x | 2.0 |
| ----- | --- | --- |
| `"$value": "red"` (any CSS color name) | resolved to `#ff0000` | **throws** `TokenParseException` — use `#ff0000` |
| `hsl` components `[210, 0.5, 0.4]` | treated as fractions, rendered mid-blue | read as spec percentages: `hsl(210 0.5% 0.4%)`, i.e. near-black. **Multiply S/L/W/B by 100** |

Everything else, grouped:

- **Color** — hex must be `#rgb` / `#rgba` / `#rrggbb` / `#rrggbbaa`; `okhsv` requires its `hex` fallback at parse time; `hex()` returns the value lowercased; out-of-range sRGB channels and alpha are clamped when reducing to sRGB (they used to overflow into invalid CSS).
- **Values now rejected instead of coerced** — `boolean` from a string (`"false"` was `true`), non-numeric or infinite dimensions/durations/numbers (`"1e999"` rendered `infpx`), `lineHeight: "normal"`, empty `gradient` / `shadow` / `fontFamily`, invalid `dashArray` entries (a dashed token used to render `solid`), duplicate token paths.
- **Output** — `CssExporter` throws on names and values it cannot emit safely; `fontFamily` quoting now also covers underscores, leading digits and non-ASCII; integers from 4504 up no longer pick up floating-point noise (`5000` was `5000.000000000001`).
- **API** — `TokenValueInterface` adds `toCss()` (implement it if you have your own value objects); `Tokens::getIterator()` is typed `\Traversable`; the Twig `hex` / `rgb` filters throw `TokenException` instead of `LogicException`; `boolean`, `string` and `link` now honour `$extensions.mode`.
- **Messages** — every parse error is prefixed `Token "path": ` and long authored content is excerpted, so tests matching exception strings need updating.
- **Cache** — keys carry a new version, so persistent pools miss once after the upgrade. That is intended.

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
