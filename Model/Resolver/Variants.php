<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;

/**
 * The variants a shopper can choose on a product page: the children of a configurable product, as the product
 * type loads them for its options (cached on the product, so the options block and the labels share one load).
 */
class Variants
{
    private const CONFIGURABLE = 'configurable';

    /**
     * @param ProductInterface $product
     * @return ProductInterface[]
     */
    public function getChildren(ProductInterface $product): array
    {
        if (!$product instanceof Product || $product->getTypeId() !== self::CONFIGURABLE) {
            return [];
        }
        $type = $product->getTypeInstance();
        if (!method_exists($type, 'getUsedProducts')) {
            return [];
        }

        return array_values(array_filter(
            (array) $type->getUsedProducts($product),
            static fn ($child): bool => $child instanceof ProductInterface && (int) $child->getId() > 0
        ));
    }
}
