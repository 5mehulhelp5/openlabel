<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Page types of "Hide labels on these pages" (keys of \Iranimij\OpenLabel\ViewModel\Labels::PAGE_TYPES).
 */
class HiddenPages implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'home', 'label' => __('Home page')],
            ['value' => 'category', 'label' => __('Category pages')],
            ['value' => 'search', 'label' => __('Search results')],
            ['value' => 'product', 'label' => __('Product pages')],
            ['value' => 'cms', 'label' => __('Other content pages')],
            ['value' => 'cart', 'label' => __('Shopping cart')],
            ['value' => 'wishlist', 'label' => __('Wish list')],
            ['value' => 'compare', 'label' => __('Compare products')],
        ];
    }
}
