<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit;

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

    public function testGermanTranslatesEveryStorefrontStringAndNothingUnknown(): void
    {
        $german = $this->csv('de_DE');

        foreach (self::STOREFRONT as $phrase) {
            self::assertArrayHasKey($phrase, $german);
            self::assertNotSame($phrase, $german[$phrase], $phrase . ' is not translated');
        }
        self::assertSame([], array_values(array_diff(array_keys($german), array_keys($this->csv('en_US')))));
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
