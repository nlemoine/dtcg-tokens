<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Export;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Internal\Str;
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
 * unbalanced brackets, unterminated strings and trailing escapes. The
 * constructor holds `$prefix` and `$selector` to the same standard: both are
 * emitted verbatim too.
 */
final readonly class CssExporter
{
    /**
     * Characters no selector or declaration value may contain: `;`, `{`, `}`
     * end or nest the surrounding structure, `<` and `>` can escape the
     * `<style>` element (HTML raw text, scanned for "</style" with no CSS
     * awareness), `/*` opens a comment, and C0/DEL control characters include
     * the newlines that terminate a CSS string.
     */
    private const string FORBIDDEN = '#[;{}<>]|/\*|[\x00-\x1F\x7F]#';

    /**
     * Selector variant of {@see self::FORBIDDEN}: `>` is the child combinator
     * and must stay allowed. The `<style>` escape only needs the literal
     * sequence "</style", so rejecting `<` alone still closes it.
     */
    private const string FORBIDDEN_IN_SELECTOR = '#[;{}<]|/\*|[\x00-\x1F\x7F]#';

    public function __construct(
        private string $prefix = '',
        private string $selector = ':root',
    ) {
        // A bad prefix or selector would otherwise surface at export time as
        // a per-token error blaming the token, or as silently broken CSS.
        if ($prefix !== '' && preg_match('/^[a-zA-Z0-9_\x80-\xFF-]+$/D', $prefix) !== 1) {
            throw TokenException::invalidValue(\sprintf(
                'Invalid CSS custom property prefix "%s"; allowed characters are A-Z, a-z, 0-9, "_", "-" and non-ASCII.',
                Str::excerpt($prefix),
            ));
        }

        if (trim($selector) === '') {
            throw TokenException::invalidValue('CSS selector must not be empty.');
        }

        if (preg_match(self::FORBIDDEN_IN_SELECTOR, $selector) === 1) {
            throw TokenException::invalidValue(\sprintf(
                'Invalid CSS selector "%s": it contains ";", "{", "}", "<", "/*" or a control character.',
                Str::excerpt($selector),
            ));
        }

        $reason = $this->unbalancedReason($selector);
        if ($reason !== null) {
            throw TokenException::invalidValue(\sprintf(
                'Invalid CSS selector "%s": %s.',
                Str::excerpt($selector),
                $reason,
            ));
        }
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
                    Str::excerpt($seen[$name]),
                    Str::excerpt($path),
                    Str::excerpt($name),
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
                Str::excerpt($path),
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
        if (preg_match(self::FORBIDDEN, $css) === 1) {
            throw $this->cannotExport($path, 'it contains characters that would break out of a CSS declaration (";", "{", "}", "<", ">", "/*" or a control character)');
        }

        $reason = $this->unbalancedReason($css);
        if ($reason !== null) {
            throw $this->cannotExport($path, $reason);
        }

        return $css;
    }

    /**
     * Why a fragment cannot be emitted verbatim — unbalanced brackets, an
     * unterminated string, or a trailing escape, each of which swallows the
     * emitted terminator and whatever follows — or null when it is safe.
     */
    private function unbalancedReason(string $css): ?string
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
                    return 'it ends with an unterminated escape ("\\")';
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
                return 'its brackets are unbalanced';
            }
        }

        if ($quote !== null) {
            return \sprintf('it contains an unterminated string (%s)', $quote);
        }

        if ($stack !== []) {
            return 'its brackets are unbalanced';
        }

        return null;
    }

    private function cannotExport(string $path, string $reason): \n5s\DtcgTokens\Exception\TokenParseException
    {
        return TokenException::invalidValue(\sprintf('Token "%s" value cannot be exported: %s.', Str::excerpt($path), $reason));
    }
}
