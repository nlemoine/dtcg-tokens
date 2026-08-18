<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;

final readonly class FontFamilyValue implements TokenValueInterface
{
    use ResolvesModes;

    /**
     * @internal
     *
     * @param list<string> $families
     * @param array<string, self>|null $modes
     */
    public function __construct(
        private array $families,
        private ?array $modes = null,
    ) {
        if ($families === []) {
            // An empty list would render "--x: ;" - invalid CSS.
            throw TokenException::invalidValue('FontFamily must contain at least one family.');
        }

        foreach ($families as $family) {
            if ($family === '') {
                throw TokenException::invalidValue('FontFamily entries must not be empty.');
            }
        }
    }

    public function __toString(): string
    {
        return implode(', ', array_map($this->quoteIfNeeded(...), $this->families));
    }

    public function toCss(): string
    {
        return $this->__toString();
    }

    /**
     * @return list<string>
     */
    public function families(): array
    {
        return $this->families;
    }

    /**
     * Bare CSS-ident words (generic keywords, single-word families like
     * `-apple-system`) pass through; anything else becomes a CSS string
     * with backslashes and double quotes escaped.
     */
    private function quoteIfNeeded(string $family): string
    {
        if (preg_match('/^-?[a-zA-Z][a-zA-Z0-9-]*$/D', $family) === 1) {
            return $family;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $family) . '"';
    }
}
