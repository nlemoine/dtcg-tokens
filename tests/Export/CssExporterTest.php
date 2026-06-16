<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Export;

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
