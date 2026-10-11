<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

/**
 * The price index join shared by OnSale and PriceRange: one alias, one condition, joined once per query.
 */
trait PriceIndexJoin
{
    public const PRICE_ALIAS = 'ol_price';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTablesToJoin()
    {
        return [
            self::PRICE_ALIAS => [
                'name' => 'catalog_product_index_price',
                'condition' => sprintf(
                    '%1$s.entity_id = e.entity_id AND %1$s.website_id = %2$d AND %1$s.customer_group_id = %3$d',
                    self::PRICE_ALIAS,
                    $this->websiteId(),
                    $this->customerGroupId()
                ),
                'columns' => [],
            ],
        ];
    }

    /**
     * Products without a price index row (e.g. out of stock on some Magento versions) never match a price condition.
     *
     * @return string
     */
    public function getSqlGuard(): ?string
    {
        return self::PRICE_ALIAS . '.entity_id IS NOT NULL';
    }

    /**
     * @return bool
     */
    public function requiresCustomerGroup(): bool
    {
        return true;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array{float, float} regular price, final price
     */
    private function prices(\Magento\Catalog\Model\Product $product): array
    {
        $price = $product->getData('price');
        $final = $product->getData('final_price');

        return [
            (float) ($price ?? $product->getPrice()),
            (float) ($final ?? $product->getFinalPrice()),
        ];
    }
}
