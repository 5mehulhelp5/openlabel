<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Design hover effects, shown while the shopper points at the product (CSS only, off for reduced motion).
 */
class HoverEffect implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'grow', 'label' => __('Grow slightly')],
            ['value' => 'lift', 'label' => __('Lift up')],
        ];
    }
}
