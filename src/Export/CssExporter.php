<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Export;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\TokenValueInterface;

/**
 * Renders a {@see Tokens} collection as CSS custom properties.
 *
 * Each token path becomes a custom property: `color.primary` -> `--color-primary`.
 * The value's CSS form is produced by casting the value object to string.
 *
 * Names and values are emitted verbatim, so anything that could break out of
 * its declaration (`;`, `{`, `}`, a comment opener, or non-ident name
 * characters) is rejected with a TokenException rather than escaped —
 * silent mangling would create colliding names and altered values.
 */
final readonly class CssExporter
{
    public function __construct(
        private string $prefix = '',
        private string $selector = ':root',
    ) {
    }

    /**
     * Accepts a {@see Tokens} collection or any iterable of path => value —
     * a generator works, enabling filtered/subset exports.
     *
     * @param iterable<string, TokenValueInterface> $tokens
     */
    public function export(iterable $tokens): string
    {
        $lines = [];
        /** @var array<string, string> $seen Custom property name => token path */
        $seen = [];
        foreach ($tokens as $path => $value) {
            $name = $this->varName($path);
            if (isset($seen[$name])) {
                throw TokenException::invalidValue(\sprintf(
                    'Tokens "%s" and "%s" both map to the CSS custom property "%s".',
                    $seen[$name],
                    $path,
                    $name,
                ));
            }

            $seen[$name] = $path;
            $lines[] = \sprintf('  %s: %s;', $name, $this->cssValue($path, $value->toCss()));
        }

        // An empty collection would otherwise yield ":root {\n\n}\n" (a stray
        // blank line); collapse it to a clean empty block instead.
        if ($lines === []) {
            return \sprintf("%s {\n}\n", $this->selector);
        }

        return \sprintf("%s {\n%s\n}\n", $this->selector, implode("\n", $lines));
    }

    private function varName(string $path): string
    {
        // DTCG token paths are dot-separated identifiers; dots and spaces map
        // to hyphens. The result must stay within CSS custom-property-name
        // characters (ASCII ident chars or any non-ASCII byte).
        $slug = str_replace(['.', ' '], '-', $path);
        $name = ($this->prefix !== '' ? $this->prefix . '-' : '') . $slug;

        if (preg_match('/^[a-zA-Z0-9_\x80-\xFF-]+$/D', $name) !== 1) {
            throw TokenException::invalidValue(\sprintf(
                'Token path "%s" cannot be exported as a CSS custom property name; allowed characters are A-Z, a-z, 0-9, "_", "-", ".", space and non-ASCII.',
                $path,
            ));
        }

        return '--' . $name;
    }

    /**
     * Custom properties accept nearly any token stream, so the value is
     * emitted verbatim — which is exactly why declaration terminators and
     * comment openers must be refused.
     */
    private function cssValue(string $path, string $css): string
    {
        if (preg_match('#[;{}]|/\*#', $css) === 1) {
            throw TokenException::invalidValue(\sprintf(
                'Token "%s" value cannot be exported: it contains characters that would break out of a CSS declaration (";", "{", "}" or "/*").',
                $path,
            ));
        }

        return $css;
    }
}
