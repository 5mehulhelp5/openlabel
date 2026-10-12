<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Variable;

/**
 * One-line explanations of the built-in variables, shown where they are inserted (08 · UX Spec §6), plus the
 * sample values the live preview fills in.
 */
class Help
{
    /**
     * @return array<string, array{token: string, description: string, sample: string}>
     */
    public function getVariables(): array
    {
        $rows = [
            'SAVE_PERCENT' => [__('Discount in percent, e.g. 25'), '25'],
            'SAVE_AMOUNT' => [__('Discount as an amount in the shop currency'), '€10.00'],
            'PRICE' => [__('Regular price'), '€39.99'],
            'SPECIAL_PRICE' => [__('Special price, when one is set'), '€29.99'],
            'FINAL_PRICE' => [__('Price the shopper pays'), '€29.99'],
            'STOCK_QTY' => [__('Quantity left to sell'), '3'],
            'NEW_FOR' => [__('Days the product stays "new"'), '12'],
            'SKU' => [__('Product SKU'), 'MJ01'],
            'ATTR:code' => [__('Any product attribute, e.g. {ATTR:color}'), 'Blue'],
            'BR' => [__('Line break (labels show at most two lines)'), "\n"],
            'SPECIAL_ENDS_IN' => [__('Days until the special price ends'), '3'],
            'SPECIAL_END_DATE' => [__('Date the special price ends'), '31/10/2026'],
            'RATING' => [__('Average rating out of 5'), '4.6'],
            'REVIEW_COUNT' => [__('Number of approved reviews'), '128'],
            'SOLD_LAST_30D' => [__('Units sold in the last 30 days'), '57'],
            'CATEGORY_NAME' => [__('Category being browsed, or the product\'s main category'), 'Jackets'],
            'BRAND' => [__('Brand (attribute set under Stores › Configuration › OpenLabel)'), 'Acme'],
        ];
        $variables = [];
        foreach ($rows as $code => [$description, $sample]) {
            $variables[$code] = ['token' => '{' . $code . '}', 'description' => (string) $description, 'sample' => $sample];
        }

        return $variables;
    }
}
