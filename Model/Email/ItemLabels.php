<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Email;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Labels of the "Order emails" area for one order item (10 · Front-end Review FE12): text and shape designs as
 * inline-styled spans (email clients ignore stylesheets), resolved for the order's store view and customer group.
 * Image designs are left out: many email clients block images.
 */
class ItemLabels
{
    private const HEX = '/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/';
    private const RADIUS = ['pill' => '999px', 'circle' => '999px', 'rectangle' => '3px', 'ribbon' => '2px', 'corner' => '3px'];
    private const BASE_STYLE = 'display:inline-block;margin:0 4px 4px 0;padding:2px 8px;font-size:12px;font-weight:600;line-height:1.4;';

    /**
     * @param LabelResolverInterface $resolver
     * @param Renderer $renderer
     * @param StoreManagerInterface $storeManager
     * @param TypedReader $config
     */
    public function __construct(
        private readonly LabelResolverInterface $resolver,
        private readonly Renderer $renderer,
        private readonly StoreManagerInterface $storeManager,
        private readonly TypedReader $config
    ) {
    }

    /**
     * @param OrderItemInterface $item
     * @return string a paragraph of labels, or an empty string
     */
    public function forOrderItem(OrderItemInterface $item): string
    {
        $product = $item instanceof OrderItem ? $item->getProduct() : null;
        $order = $item instanceof OrderItem ? $item->getOrder() : null;
        if (!$product instanceof ProductInterface || !$order instanceof Order || (int) $product->getId() === 0) {
            return '';
        }
        $storeId = (int) $order->getStoreId();
        if (!$this->config->getBool(Labels::CONFIG_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
            return '';
        }
        $groupId = (int) $order->getCustomerGroupId();
        $store = $this->storeManager->getStore($storeId);
        $context = new Context($storeId, $store instanceof Store ? (int) $store->getWebsiteId() : 0, $groupId);
        $spans = '';
        $labels = $this->resolver->getForProducts([(int) $product->getId()], $storeId, $groupId)[(int) $product->getId()] ?? [];
        foreach ($labels as $label) {
            $design = $label->getDesign();
            if ($label->getPlacement()->getArea() !== PlacementInterface::AREA_EMAIL
                || $design->getType() === DesignInterface::TYPE_IMAGE
            ) {
                continue;
            }
            $rendered = $this->renderer->render((string) $label->getText(), $product, $context);
            if ($rendered->getHtml() === '' || ($label->isHideOnZeroVariable() && $rendered->hasEmptyVariable())) {
                continue;
            }
            $spans .= '<span style="' . $this->style($design) . '">' . $rendered->getHtml() . '</span>';
        }

        return $spans === '' ? '' : '<p class="openlabel-email" style="margin:4px 0 0;">' . $spans . '</p>';
    }

    /**
     * @param DesignInterface $design
     * @return string inline CSS from validated values only
     */
    private function style(DesignInterface $design): string
    {
        return self::BASE_STYLE
            . 'background:' . $this->colour($design->getBgColor(), '#111827') . ';'
            . 'color:' . $this->colour($design->getTextColor(), '#ffffff') . ';'
            . 'border-radius:' . (self::RADIUS[(string) $design->getShape()] ?? '3px') . ';';
    }

    /**
     * @param string|null $colour
     * @param string $fallback
     * @return string
     */
    private function colour(?string $colour, string $fallback): string
    {
        $colour = strtolower(trim((string) $colour));

        return preg_match(self::HEX, $colour) === 1 ? $colour : $fallback;
    }
}
