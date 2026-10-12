<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Model\Design\LabelPack;
use Iranimij\OpenLabel\Model\Design\SystemDesignCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * en_US.csv lists every translatable string of the module; de_DE.csv translates the storefront strings.
 */
class I18nTest extends TestCase
{
    /** Strings shoppers can see (variables {SPECIAL_ENDS_IN}, {NEW_FOR}, {COUNTDOWN}). */
    private const STOREFRONT = ['1 day', '%1 days', '1 hour', '%1 hours', 'Ends %1'];

    public function testEveryPhraseInTheCodeIsInEnUs(): void
    {
        $missing = array_values(array_diff($this->phrasesInCode(), array_keys($this->csv('en_US'))));

        self::assertSame([], $missing, 'add these to i18n/en_US.csv');
    }

    public function testEnUsHoldsEveryTextShoppersSee(): void
    {
        $missing = array_values(array_diff($this->shopperPhrases(), array_keys($this->csv('en_US'))));

        self::assertSame([], $missing, 'add these to i18n/en_US.csv');
    }

    /**
     * Label packs (1.1): the built-in designs' texts and the storefront phrases in every shipped language.
     *
     * @dataProvider packs
     */
    #[DataProvider('packs')]
    public function testEveryLabelPackTranslatesWhatShoppersSee(string $locale): void
    {
        $pack = $this->csv($locale);

        foreach ($this->shopperPhrases() as $phrase) {
            self::assertArrayHasKey($phrase, $pack, $locale . ': ' . $phrase);
            self::assertNotSame('', trim($pack[$phrase]), $locale . ': ' . $phrase);
            self::assertSame($this->tokens($phrase), $this->tokens($pack[$phrase]), $locale . ' keeps the variables of ' . $phrase);
        }
        foreach (self::STOREFRONT as $phrase) {
            self::assertNotSame($phrase, $pack[$phrase], $locale . ': ' . $phrase . ' is not translated');
        }
        self::assertSame([], array_values(array_diff(array_keys($pack), array_keys($this->csv('en_US')))), $locale . ': unknown phrases');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function packs(): array
    {
        return ['German' => ['de_DE'], 'French' => ['fr_FR'], 'Spanish' => ['es_ES'], 'Dutch' => ['nl_NL'], 'Italian' => ['it_IT']];
    }

    /**
     * @return string[] storefront phrases plus every built-in design text that has words besides its variables,
     *                  with the label pack prefix
     */
    private function shopperPhrases(): array
    {
        $phrases = self::STOREFRONT;
        foreach ((new SystemDesignCatalog())->getDefinitions() as $definition) {
            $text = (string) $definition[DesignInterface::STORE_TEXTS][DesignInterface::DEFAULT_STORE_ID]['text'];
            if (preg_match('/\p{L}/u', (string) preg_replace('/\{[A-Z_]+\}/', '', $text)) === 1) {
                $phrases[] = LabelPack::PREFIX . $text;
            }
        }

        return array_values(array_unique($phrases));
    }

    /**
     * @param string $text
     * @return string[] the {VARIABLES} and %1 placeholders, sorted
     */
    private function tokens(string $text): array
    {
        preg_match_all('/\{[A-Z_]+\}|%\d/', $text, $matches);
        $tokens = $matches[0];
        sort($tokens);

        return $tokens;
    }

    /**
     * @return string[]
     */
    private function phrasesInCode(): array
    {
        $root = dirname(__DIR__, 2);
        $phrases = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if (!preg_match('/\.(php|phtml)$/', $path) || preg_match('#/(Test|vendor)/#', substr($path, strlen($root)))) {
                continue;
            }
            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($path), $matches);
            foreach ($matches[1] as $phrase) {
                $phrases[stripslashes($phrase)] = true;
            }
        }
        $phrases = array_keys($phrases);
        sort($phrases);

        return $phrases;
    }

    /**
     * @return array<string, string>
     */
    private function csv(string $locale): array
    {
        $rows = [];
        $handle = fopen(dirname(__DIR__, 2) . '/i18n/' . $locale . '.csv', 'rb');
        self::assertNotFalse($handle, $locale . '.csv is missing');
        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (isset($row[0], $row[1])) {
                $rows[$row[0]] = $row[1];
            }
        }
        fclose($handle);

        return $rows;
    }
}
