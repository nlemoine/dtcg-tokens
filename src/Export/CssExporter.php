<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Export;

use n5s\DtcgTokens\Tokens;

/**
 * Renders a {@see Tokens} collection as CSS custom properties.
 *
 * Each token path becomes a custom property: `color.primary` -> `--color-primary`.
 * The value's CSS form is produced by casting the value object to string.
 */
final readonly class CssExporter
{
    public function __construct(
        private string $prefix = '',
        private string $selector = ':root',
    ) {
    }

    public function export(Tokens $tokens): string
    {
        $lines = [];
        foreach ($tokens as $path => $value) {
            $lines[] = \sprintf('  %s: %s;', $this->varName($path), (string) $value);
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
        // DTCG token paths are dot-separated identifiers; we only normalise dots
        // and spaces to hyphens and do not escape arbitrary CSS-ident characters.
        $slug = str_replace(['.', ' '], '-', $path);

        return '--' . ($this->prefix !== '' ? $this->prefix . '-' : '') . $slug;
    }
}
