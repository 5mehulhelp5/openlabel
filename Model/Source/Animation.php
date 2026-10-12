<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Design entry animations (CSS only, off for reduced motion).
 */
class Animation implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'fade', 'label' => __('Fade in')],
            ['value' => 'pop', 'label' => __('Pop in')],
        ];
    }
}
