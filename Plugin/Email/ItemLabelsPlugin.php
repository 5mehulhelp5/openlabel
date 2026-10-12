<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Plugin\Email;

use Iranimij\OpenLabel\Model\Email\ItemLabels;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Order, invoice, shipment and credit memo emails: the item's labels go right under its name
 * (`<p class="product-name">` of the core item templates). Custom templates without it stay as they are.
 */
class ItemLabelsPlugin
{
    private const NAME = 'class="product-name"';

    /**
     * @param ItemLabels $itemLabels
     */
    public function __construct(private readonly ItemLabels $itemLabels)
    {
    }

    /**
     * @param Template $subject order email item renderer (DefaultOrder) or invoice/shipment/credit memo (DefaultItems)
     * @param mixed $result
     * @return mixed
     */
    public function afterToHtml(Template $subject, mixed $result): mixed
    {
        if (!is_string($result) || !str_contains($result, self::NAME)) {
            return $result;
        }
        $item = $subject->getData('item') ?? (method_exists($subject, 'getItem') ? $subject->getItem() : null);
        if ($item !== null && !$item instanceof OrderItemInterface && method_exists($item, 'getOrderItem')) {
            $item = $item->getOrderItem();
        }
        if (!$item instanceof OrderItemInterface) {
            return $result;
        }
        $labels = $this->itemLabels->forOrderItem($item);
        $end = $labels === '' ? false : strpos($result, '</p>', (int) strpos($result, self::NAME));
        if ($end === false) {
            return $result;
        }
        $end += 4;

        return substr($result, 0, $end) . $labels . substr($result, $end);
    }
}
