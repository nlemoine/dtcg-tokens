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
 * its declaration — or out of the surrounding `<style>` element — is
 * rejected with a TokenException rather than escaped: silent mangling would
 * create colliding names and altered values. That covers non-ident name
 * characters, `;`, `{`, `}`, `<`, `>`, comment openers, control characters,
 * unbalanced brackets, unterminated strings and trailing escapes.
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
            // PHP canonicalizes a numeric path ("4") to an int array key.
            $path = (string) $path;
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
     * Custom properties accept nearly any token stream and the value is
     * emitted verbatim, so anything able to escape its declaration — or the
     * surrounding <style> element — is refused.
     *
     * A denylist alone is not enough: an unterminated string or function
     * token swallows everything up to the next quote/paren or EOF, so the
     * value is scanned for balance and termination too.
     */
    private function cssValue(string $path, string $css): string
    {
        // ; { }  end or nest a declaration.
        // < >    escape the <style> element: it is HTML raw text, scanned for
        //        "</style" with no CSS awareness, so no CSS guard applies.
        // /*     opens a comment.
        // C0/DEL control characters, including the newlines that terminate a
        //        CSS string.
        if (preg_match('#[;{}<>]|/\*|[\x00-\x1F\x7F]#', $css) === 1) {
            throw $this->cannotExport($path, 'it contains characters that would break out of a CSS declaration (";", "{", "}", "<", ">", "/*" or a control character)');
        }

        $this->assertBalancedAndTerminated($path, $css);

        return $css;
    }

    /**
     * Reject values whose brackets are unbalanced, whose strings are left
     * open, or which end mid-escape — each of those swallows the emitted
     * terminator and the declarations that follow.
     */
    private function assertBalancedAndTerminated(string $path, string $css): void
    {
        /** @var list<string> $stack Expected closing brackets, innermost last */
        $stack = [];
        $quote = null;
        $length = \strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '\\') {
                // A trailing backslash escapes the terminator we append.
                if ($i + 1 >= $length) {
                    throw $this->cannotExport($path, 'it ends with an unterminated escape ("\\")');
                }

                $i++;

                continue;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }

            if ($char === '(' || $char === '[') {
                $stack[] = $char === '(' ? ')' : ']';

                continue;
            }

            if (($char === ')' || $char === ']') && array_pop($stack) !== $char) {
                throw $this->cannotExport($path, 'its brackets are unbalanced');
            }
        }

        if ($quote !== null) {
            throw $this->cannotExport($path, \sprintf('it contains an unterminated string (%s)', $quote));
        }

        if ($stack !== []) {
            throw $this->cannotExport($path, 'its brackets are unbalanced');
        }
    }

    private function cannotExport(string $path, string $reason): \n5s\DtcgTokens\Exception\TokenParseException
    {
        return TokenException::invalidValue(\sprintf('Token "%s" value cannot be exported: %s.', $path, $reason));
    }
}
