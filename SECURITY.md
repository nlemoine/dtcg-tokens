# Security

## Trust model

This library is designed for **trusted, developer-authored token files**
committed alongside application code. Three boundaries to know about:

- **Token files** are parsed with strict validation (types, ranges, hex
  format), and clear `TokenException`s are thrown on malformed input — but
  validation is a robustness feature, not a sandbox. If your token JSON comes
  from an untrusted source (CMS content, user upload, third-party export
  pipeline), validate and constrain it upstream.
- **`CssExporter`** emits custom property names and values verbatim, so it
  refuses — by throwing, never by escaping — anything able to break out of
  its declaration or out of the `<style>` element: invalid name characters,
  `;`, `{`, `}`, `<`, `>`, `/*`, control characters, unbalanced brackets,
  unterminated strings, and trailing escapes.
- **Value objects rendered directly** (`(string)` / `toCss()`, including the
  Twig `token()` function and the `hex` / `rgb` filters) bypass that check
  entirely. Twig autoescaping does not save you here: its default `html`
  strategy leaves `;`, `{` and `}` untouched, which is full rule injection
  inside a `<style>` block. Twig's `css` escaping strategy is safe but also
  escapes CSS-meaningful characters (`#ff0000` becomes `\23 ff0000`), so the
  declaration silently stops working — do not use it for token values. The
  reliable pattern: do not interpolate token values into `<style>` blocks at
  all. Emit the stylesheet with `CssExporter` and reference tokens from
  templates via `var(--token-name)`.
- **The PSR-6 cache pool** is trusted and unvalidated: cached payloads are
  unserialized as-is. Do not point the factory at a pool writable by
  untrusted code.

## Reporting a vulnerability

Please report suspected vulnerabilities privately via
[GitHub security advisories](https://github.com/nlemoine/dtcg-tokens/security/advisories/new).
Do not open a public issue for security reports.
