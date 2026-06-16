<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Twig;

use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Twig\TokenExtension;
use n5s\DtcgTokens\Value\DimensionValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(TokenExtension::class)]
final class TokenExtensionTest extends TestCase
{
    public function testHexFilter(): void
    {
        $twig = $this->twig([
            't' => '{{ token("color.primary")|hex }}',
        ]);

        self::assertSame('#ff0000', $twig->render('t'));
    }

    public function testRgbFilter(): void
    {
        $twig = $this->twig([
            't' => '{{ token("color.primary")|rgb }}',
        ]);

        self::assertSame('rgb(255 0 0)', $twig->render('t'));
    }

    public function testRgbFilterWithAlpha(): void
    {
        $twig = $this->twig([
            't' => '{{ token("color.primary")|rgb(0.5) }}',
        ]);

        self::assertSame('rgb(255 0 0 / 0.5)', $twig->render('t'));
    }

    public function testDimensionTokenStringifiesInTemplate(): void
    {
        $twig = $this->twig([
            't' => '{{ token("space.md") }}',
        ]);

        self::assertSame('16px', $twig->render('t'));
    }

    public function testHexFilterOnNonColorTokenThrows(): void
    {
        $twig = $this->twig([
            't' => '{{ token("space.md")|hex }}',
        ]);

        try {
            $twig->render('t');
            self::fail('Expected a RuntimeError to be thrown.');
        } catch (RuntimeError $error) {
            $previous = $error->getPrevious();
            self::assertInstanceOf(\LogicException::class, $previous);
            self::assertSame(
                \sprintf('The "hex" filter can only be used on color tokens, got %s.', DimensionValue::class),
                $previous->getMessage(),
            );
        }
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates): Environment
    {
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => '#ff0000',
                ],
            ],
            'space' => [
                '$type' => 'dimension',
                'md' => [
                    '$value' => [
                        'value' => 16,
                        'unit' => 'px',
                    ],
                ],
            ],
        ]);

        $environment = new Environment(new ArrayLoader($templates));
        $environment->addExtension(new AttributeExtension(TokenExtension::class));
        $environment->addRuntimeLoader(new FactoryRuntimeLoader([
            TokenExtension::class => static fn (): TokenExtension => new TokenExtension($tokens),
        ]));

        return $environment;
    }
}
