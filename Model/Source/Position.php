<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Label positions: the nine on the product image, twelve in the bands around it and three in the product text.
 */
class Position implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int|string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        $labels = [
            'tl' => __('Top left'),
            'tc' => __('Top centre'),
            'tr' => __('Top right'),
            'ml' => __('Middle left'),
            'mc' => __('Centre'),
            'mr' => __('Middle right'),
            'bl' => __('Bottom left'),
            'bc' => __('Bottom centre'),
            'br' => __('Bottom right'),
            'above_l' => __('Above the image, left'),
            'above_c' => __('Above the image, centre'),
            'above_r' => __('Above the image, right'),
            'below_l' => __('Below the image, left'),
            'below_c' => __('Below the image, centre'),
            'below_r' => __('Below the image, right'),
            'left_t' => __('Left of the image, top'),
            'left_m' => __('Left of the image, middle'),
            'left_b' => __('Left of the image, bottom'),
            'right_t' => __('Right of the image, top'),
            'right_m' => __('Right of the image, middle'),
            'right_b' => __('Right of the image, bottom'),
            'near_price' => __('Next to the price'),
            'near_cart' => __('Next to Add to Cart'),
            'under_name' => __('Under the name'),
        ];
        foreach ($labels as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return $options;
    }
}
