<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Placement areas (1.0: listing and product page).
 */
class Area implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int|string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        $labels = [
            'listing' => __('Category, search and widgets'),
            'product' => __('Product page'),
            'cart' => __('Shopping cart'),
            'minicart' => __('Mini cart'),
            'wishlist' => __('Wish list'),
            'compare' => __('Compare products'),
            'email' => __('Order emails'),
        ];
        foreach ($labels as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return $options;
    }
}
