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
  inside a `<style>` block. Prefer `CssExporter` for stylesheet output; if
  you must interpolate a token into CSS in a template, use the `css`
  escaping strategy (`{{ token('x')|e('css') }}`).
- **The PSR-6 cache pool** is trusted and unvalidated: cached payloads are
  unserialized as-is. Do not point the factory at a pool writable by
  untrusted code.

## Reporting a vulnerability

Please report suspected vulnerabilities privately via
[GitHub security advisories](https://github.com/nlemoine/dtcg-tokens/security/advisories/new).
Do not open a public issue for security reports.
