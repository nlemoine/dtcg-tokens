# Changelog

## [2.0.1](https://github.com/nlemoine/dtcg-tokens/compare/2.0.0...2.0.1) (2026-08-20)


### Bug Fixes

* tie the cache key version to the released version ([03c2a8e](https://github.com/nlemoine/dtcg-tokens/commit/03c2a8e87574b4d170816901a3596d47dcf263a5))

## [2.0.0](https://github.com/nlemoine/dtcg-tokens/compare/1.0.0...2.0.0) (2026-08-20)


### ⚠ BREAKING CHANGES

* strict parse collisions, exporter config validation, bounded errors
* complete the review fixes and finish the 2.0 API surface
* hsl/hwb components use DTCG spec ranges (0-100 for S/L/W/B) instead of 0..1 fractions; input that was previously coerced or silently dropped now throws TokenException; CssExporter throws on unsafe names and values; TokenValueInterface adds toCss(); the Twig hex/rgb filters throw TokenException instead of LogicException.

### Features

* resilient, versioned and expirable token cache ([6f12ec3](https://github.com/nlemoine/dtcg-tokens/commit/6f12ec381cfb3eced9903bb14c1f63cea1c26a01))
* strict DTCG validation, spec color ranges and a mode-aware API ([0559537](https://github.com/nlemoine/dtcg-tokens/commit/0559537bac4a43a595c6293295134a1f1d93dd54))
* strict parse collisions, exporter config validation, bounded errors ([8c041b2](https://github.com/nlemoine/dtcg-tokens/commit/8c041b2c13b425e5d23581c327c2d04996089772))


### Bug Fixes

* address review feedback and align with the current toolchain ([a7e0573](https://github.com/nlemoine/dtcg-tokens/commit/a7e05736ceecb1ed6f45b79525211a86bfb02782))
* close review findings on CSS export, alias depth and caching ([9530a37](https://github.com/nlemoine/dtcg-tokens/commit/9530a37a9888779b32d29b02502f3848eaf7a1a8))
* complete the review fixes and finish the 2.0 API surface ([712ef7d](https://github.com/nlemoine/dtcg-tokens/commit/712ef7de8dde6d7ec2ecc9e716046a61c2b64a45))
* require PHPUnit 13.2 for the assertion API the tests use ([8baebe8](https://github.com/nlemoine/dtcg-tokens/commit/8baebe83722ceef8549cc2c9d8e515c5e8d76c89))

## 1.0.0 (2026-06-16)


### Features

* initial release ([d8078a2](https://github.com/nlemoine/dtcg-tokens/commit/d8078a2c0bd254f285ff9193aa8f460a3d64ab3e))


### Miscellaneous Chores

* initial commit ([5c8c83e](https://github.com/nlemoine/dtcg-tokens/commit/5c8c83e2d0c7e5a28cc6fdc43d89224101108fe5))
