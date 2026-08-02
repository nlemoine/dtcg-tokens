# Security

## Trust model

This library is designed for **trusted, developer-authored token files**
committed alongside application code. Three boundaries to know about:

- **Token files** are parsed with strict validation (types, ranges, hex
  format), and clear `TokenException`s are thrown on malformed input — but
  validation is a robustness feature, not a sandbox. If your token JSON comes
  from an untrusted source (CMS content, user upload, third-party export
  pipeline), validate and constrain it upstream.
- **`CssExporter`** emits custom property names and values verbatim. It
  refuses anything able to break out of its declaration (invalid name
  characters, `;`, `{`, `}`, `/*`) by throwing rather than escaping, so its
  output is safe to embed in a stylesheet. Value objects rendered directly in
  templates (`(string)` / `toCss()`) bypass that check: treat them as CSS
  fragments, not as pre-escaped output.
- **The PSR-6 cache pool** is trusted and unvalidated: cached payloads are
  unserialized as-is. Do not point the factory at a pool writable by
  untrusted code.

## Reporting a vulnerability

Please report suspected vulnerabilities privately via
[GitHub security advisories](https://github.com/nlemoine/dtcg-tokens/security/advisories/new).
Do not open a public issue for security reports.
