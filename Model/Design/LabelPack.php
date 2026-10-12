<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Design;

use Magento\Framework\TranslateInterface;

/**
 * Label packs (1.1): the texts of the built-in designs in the language of the store view, from the module's
 * i18n CSV files (en, de, fr, es, nl, it) or any theme or language pack that translates the same phrase.
 * The dictionary is shared by every module, so the phrases carry their own prefix ("Label text: Sale"): a plain
 * "Sale" or "New" would change other modules' texts, and theirs (e.g. "Sale" as a sales transaction) ours.
 * Looked up in the loaded dictionary as is: the {VARIABLES} in label texts must not go through ICU formatting.
 */
class LabelPack
{
    public const PREFIX = 'Label text: ';

    /**
     * @param TranslateInterface $translator
     */
    public function __construct(private readonly TranslateInterface $translator)
    {
    }

    /**
     * @param string $text the built-in design's default (English) text
     * @return string
     */
    public function translate(string $text): string
    {
        $translated = $this->translator->getData()[self::PREFIX . $text] ?? null;

        return is_string($translated) && $translated !== '' ? $translated : $text;
    }
}
