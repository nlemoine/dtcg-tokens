<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Twig;

use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\ColorValue;
use n5s\DtcgTokens\Value\TokenValueInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class TokenExtension
{
    public function __construct(
        private Tokens $tokens,
    ) {
    }

    #[AsTwigFunction('token')]
    public function token(string $path, ?string $mode = null): TokenValueInterface
    {
        return $this->tokens->get($path, $mode);
    }

    #[AsTwigFilter('hex')]
    public function hex(TokenValueInterface $value): string
    {
        if (! $value instanceof ColorValue) {
            throw new \LogicException(\sprintf('The "hex" filter can only be used on color tokens, got %s.', $value::class));
        }

        return $value->toHex();
    }

    #[AsTwigFilter('rgb')]
    public function rgb(TokenValueInterface $value, ?float $alpha = null): string
    {
        if (! $value instanceof ColorValue) {
            throw new \LogicException(\sprintf('The "rgb" filter can only be used on color tokens, got %s.', $value::class));
        }

        return $value->toRgb($alpha);
    }
}
