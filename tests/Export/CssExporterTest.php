<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Export;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Export\CssExporter;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\ColorValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssExporter::class)]
final class CssExporterTest extends TestCase
{
    public function testExportsTwoTokensAsCustomProperties(): void
    {
        $css = new CssExporter()->export($this->tokens());

        self::assertSame(
            <<<CSS
            :root {
              --color-primary: rgb(255 0 0);
              --space-md: 16px;
            }

            CSS,
            $css,
        );
    }

    public function testPrefixIsPrependedToEveryVariable(): void
    {
        $css = new CssExporter(prefix: 'ds')->export($this->tokens());

        self::assertStringContainsString('--ds-color-primary: rgb(255 0 0);', $css);
        self::assertStringContainsString('--ds-space-md: 16px;', $css);
    }

    public function testCustomSelectorReplacesRoot(): void
    {
        $css = new CssExporter(selector: '[data-theme]')->export($this->tokens());

        self::assertStringStartsWith('[data-theme] {', $css);
    }

    public function testEmptyCollectionEmitsSelectorWithoutBlankLine(): void
    {
        $css = new CssExporter()->export(new Tokens([]));

        self::assertSame(":root {\n}\n", $css);
    }

    public function testSpacesInPathBecomeHyphens(): void
    {
        $tokens = new Tokens([
            'color brand primary' => ColorValue::fromHex('#ff0000'),
        ]);

        $css = new CssExporter()->export($tokens);

        self::assertStringContainsString('--color-brand-primary: rgb(255 0 0);', $css);
    }

    public function testTokenPathThatWouldBreakOutOfPropertyNameIsRejected(): void
    {
        // Even a perfectly safe value is exploitable through its key: the
        // path lands verbatim in the custom property name.
        $tokens = new Tokens([
            'brand-evil}*{display:none!important}html{--m' => ColorValue::fromHex('#000000'),
        ]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('cannot be exported as a CSS custom property name');

        new CssExporter()->export($tokens);
    }

    public function testStringValueThatWouldBreakOutOfDeclarationIsRejected(): void
    {
        $tokens = Tokens::fromArray([
            'x' => [
                '$type' => 'string',
                '$value' => 'red; } body { display: none; } :root { --pwn: 1',
            ],
        ]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('break out of a CSS declaration');

        new CssExporter()->export($tokens);
    }

    public function testValueContainingCommentOpenerIsRejected(): void
    {
        $tokens = Tokens::fromArray([
            'x' => [
                '$type' => 'string',
                '$value' => 'red /* sneaky',
            ],
        ]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('break out of a CSS declaration');

        new CssExporter()->export($tokens);
    }

    public function testCollidingVariableNamesAreRejected(): void
    {
        // "a.b" and "a b" both slug to --a-b; the cascade would silently pick
        // the last declaration.
        $tokens = new Tokens([
            'a.b' => ColorValue::fromHex('#ff0000'),
            'a b' => ColorValue::fromHex('#00ff00'),
        ]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('both map to the CSS custom property "--a-b"');

        new CssExporter()->export($tokens);
    }

    public function testNonAsciiTokenPathExports(): void
    {
        // CSS custom property names allow non-ASCII identifiers.
        $tokens = Tokens::fromArray([
            'thème' => [
                '$type' => 'color',
                'primaire' => [
                    '$value' => '#ff0000',
                ],
            ],
        ]);

        $css = new CssExporter()->export($tokens);

        self::assertStringContainsString('--thème-primaire: rgb(255 0 0);', $css);
    }

    public function testThemedExportViaForMode(): void
    {
        // The primary use case for modes: rendering a [data-theme] block.
        $tokens = Tokens::fromArray([
            'color' => [
                '$type' => 'color',
                'fg' => [
                    '$value' => '#ffffff',
                    '$extensions' => [
                        'mode' => [
                            'dark' => '#000000',
                        ],
                    ],
                ],
            ],
        ]);

        $css = new CssExporter(selector: '[data-theme="dark"]')->export($tokens->forMode('dark'));

        self::assertSame(
            <<<'CSS'
            [data-theme="dark"] {
              --color-fg: rgb(0 0 0);
            }

            CSS,
            $css,
        );
    }

    private function tokens(): Tokens
    {
        return Tokens::fromArray([
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
    }
}
