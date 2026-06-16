<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Value;

use n5s\DtcgTokens\Exception\TokenException;

final readonly class StrokeStyleValue implements TokenValueInterface
{
    /**
     * The canonical DTCG/CSS stroke-style keywords (matches terrazzo).
     *
     * @var list<string>
     */
    private const array KEYWORDS = ['solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'outset', 'inset'];

    /**
     * @param list<DimensionValue> $dashArray
     * @param array<string, self>|null $modes
     */
    private function __construct(
        private ?string $keyword = null,
        private array $dashArray = [],
        private ?string $lineCap = null,
        private ?array $modes = null,
    ) {
    }

    public function __toString(): string
    {
        if ($this->keyword !== null) {
            return $this->keyword;
        }

        return $this->dashArray === [] ? 'solid' : 'dashed';
    }

    /**
     * @internal
     *
     * @param array<string, self>|null $modes
     */
    public static function fromKeyword(string $keyword, ?array $modes = null): self
    {
        if (! \in_array($keyword, self::KEYWORDS, true)) {
            throw TokenException::invalidValue(\sprintf(
                'Invalid strokeStyle keyword "%s"; expected one of %s.',
                $keyword,
                implode(', ', self::KEYWORDS),
            ));
        }

        return new self(keyword: $keyword, modes: $modes);
    }

    /**
     * @internal
     *
     * @param list<DimensionValue> $dashArray
     * @param array<string, self>|null $modes
     */
    public static function fromObject(array $dashArray, string $lineCap, ?array $modes = null): self
    {
        return new self(dashArray: $dashArray, lineCap: $lineCap, modes: $modes);
    }

    public function forMode(string $mode): static
    {
        return $this->modes[$mode] ?? $this;
    }

    /**
     * @return list<DimensionValue>
     */
    public function dashArray(): array
    {
        return $this->dashArray;
    }

    public function lineCap(): ?string
    {
        return $this->lineCap;
    }
}
