<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Export;

use n5s\DtcgTokens\Exception\TokenException;
use n5s\DtcgTokens\Export\CssExporter;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Value\ColorValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssExporter::class)]
final class CssExporterTest extends TestCase
{
    public function testExportsTwoTokensAsCustomProperties(): void
    {
        $css = new CssExporter()
            ->export($this->tokens());

        self::assertSame(
            <<<CSS
            :root {
              --color-primary: rgb(255 0 0);
              --space-md: 16px;
            }

            CSS
            ,
            $css,
        );
    }

    public function testPrefixIsPrependedToEveryVariable(): void
    {
        $css = new CssExporter(prefix: 'ds')
            ->export($this->tokens());

        self::assertStringContainsString('--ds-color-primary: rgb(255 0 0);', $css);
        self::assertStringContainsString('--ds-space-md: 16px;', $css);
    }

    public function testCustomSelectorReplacesRoot(): void
    {
        $css = new CssExporter(selector: '[data-theme]')
            ->export($this->tokens());

        self::assertStringStartsWith('[data-theme] {', $css);
    }

    public function testEmptyCollectionEmitsSelectorWithoutBlankLine(): void
    {
        $css = new CssExporter()
            ->export(new Tokens([]));

        self::assertSame(":root {\n}\n", $css);
    }

    public function testSpacesInPathBecomeHyphens(): void
    {
        $tokens = new Tokens([
            'color brand primary' => ColorValue::fromHex('#ff0000'),
        ]);

        $css = new CssExporter()
            ->export($tokens);

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
        $this->expectExceptionMessageIsOrContains('cannot be exported as a CSS custom property name');

        new CssExporter()
            ->export($tokens);
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
        $this->expectExceptionMessageIsOrContains('break out of a CSS declaration');

        new CssExporter()
            ->export($tokens);
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
        $this->expectExceptionMessageIsOrContains('break out of a CSS declaration');

        new CssExporter()
            ->export($tokens);
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
        $this->expectExceptionMessageIsOrContains('both map to the CSS custom property "--a-b"');

        new CssExporter()
            ->export($tokens);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsafeValues(): iterable
    {
        // <style> is an HTML raw-text element: the tokenizer scans for the
        // bytes "</style" with no CSS awareness, so no CSS-level guard applies.
        yield 'html breakout' => ['</style><script>alert(1)</script>'];
        yield 'angle bracket' => ['a < b'];
        // Ordinary, non-adversarial content that still breaks the declaration.
        yield 'inch mark opens a string' => ['12" display'];
        yield 'apostrophe opens a string' => ["Bob's font"];
        yield 'unclosed function token' => ['url(/logo_(v2.svg'];
        yield 'stray closing paren' => ['rgb(0 0 0))'];
        yield 'trailing backslash escapes the terminator' => ['red\\'];
        yield 'newline' => ["Foo\nBar"];
        yield 'carriage return' => ["Foo\rBar"];
        yield 'nul byte' => ["Foo\0Bar"];
    }

    #[DataProvider('provideUnsafeValues')]
    public function testValueThatCannotBeSafelyEmittedIsRejected(string $value): void
    {
        $tokens = Tokens::fromArray([
            'x' => [
                '$type' => 'string',
                '$value' => $value,
            ],
        ]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessageIsOrContains('cannot be exported');

        new CssExporter()
            ->export($tokens);
    }

    public function testLegitimateNestedAndQuotedValuesStillExport(): void
    {
        // Regression guard for the checks above: real output nests functions
        // and carries quoted strings with escaped quotes and backslashes.
        $tokens = Tokens::fromArray([
            'grad' => [
                '$type' => 'gradient',
                '$value' => [
                    [
                        'color' => '#ff0000',
                        'position' => 0,
                    ],
                    [
                        'color' => '#0000ff',
                        'position' => 1,
                    ],
                ],
            ],
            'font' => [
                '$type' => 'fontFamily',
                '$value' => ['Helvetica Neue', 'My "Quoted" Face', 'back\\slash', 'sans-serif'],
            ],
            'ease' => [
                '$type' => 'cubicBezier',
                '$value' => [0.25, 0.1, 0.25, 1.0],
            ],
        ]);

        $css = new CssExporter()
            ->export($tokens);

        self::assertStringContainsString('linear-gradient(rgb(255 0 0) 0%, rgb(0 0 255) 100%)', $css);
        self::assertStringContainsString('"Helvetica Neue", "My \"Quoted\" Face", "back\\\\slash", sans-serif', $css);
        self::assertStringContainsString('cubic-bezier(0.25, 0.1, 0.25, 1)', $css);
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

        $css = new CssExporter()
            ->export($tokens);

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

        $css = new CssExporter(selector: '[data-theme="dark"]')
            ->export($tokens->forMode('dark'));

        self::assertSame(
            <<<'CSS'
            [data-theme="dark"] {
              --color-fg: rgb(0 0 0);
            }

            CSS
            ,
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
