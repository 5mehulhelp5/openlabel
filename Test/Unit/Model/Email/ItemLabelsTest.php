<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Email;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Email\ItemLabels;
use Iranimij\OpenLabel\Model\Placement;
use Iranimij\OpenLabel\Model\Resolver\ResolvedLabel;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Rendered;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Iranimij\OpenLabel\Plugin\Email\ItemLabelsPlugin;
use Magento\Catalog\Model\Product;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Block\Order\Email\Items\Order\DefaultOrder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Labels in order, invoice, shipment and credit memo emails (10 · Front-end Review FE12): text labels only,
 * inline styles, the order's store view and customer group.
 */
class ItemLabelsTest extends TestCase
{
    /** @var array<int, array{int[], int, int}> */
    private array $calls = [];

    /** @var ResolvedLabel[] */
    private array $resolved = [];

    private bool $enabled = true;

    public function testTextLabelsOfTheEmailAreaWithInlineStyles(): void
    {
        $this->resolved = [
            $this->label(1, 'email', 'shape', 'pill', 'Sale -{SAVE_PERCENT}%', '#B91C1C', '#ffffff'),
            $this->label(2, 'email', 'image', null, null, null, null),
            $this->label(3, 'listing', 'text', null, 'Not in emails', '#000000', '#ffffff'),
            $this->label(4, 'email', 'text', 'rectangle', 'Gift', 'red;}x{', '#1f2937'),
        ];

        $html = $this->itemLabels()->forOrderItem($this->orderItem());

        self::assertSame([[[42], 3, 1]], $this->calls, 'the order\'s store view and customer group');
        self::assertSame(
            '<p class="openlabel-email" style="margin:4px 0 0;">'
            . '<span style="display:inline-block;margin:0 4px 4px 0;padding:2px 8px;font-size:12px;font-weight:600;line-height:1.4;'
            . 'background:#b91c1c;color:#ffffff;border-radius:999px;">[Sale -{SAVE_PERCENT}%]</span>'
            . '<span style="display:inline-block;margin:0 4px 4px 0;padding:2px 8px;font-size:12px;font-weight:600;line-height:1.4;'
            . 'background:#111827;color:#1f2937;border-radius:3px;">[Gift]</span></p>',
            $html,
            'image labels and other areas are left out; colours that are not hex fall back'
        );
    }

    public function testNoLabelsNoMarkup(): void
    {
        self::assertSame('', $this->itemLabels()->forOrderItem($this->orderItem()));
        $this->enabled = false;
        $this->resolved = [$this->label(1, 'email', 'text', null, 'Sale', '#b91c1c', '#ffffff')];
        self::assertSame('', $this->itemLabels()->forOrderItem($this->orderItem()), 'module switched off for the store');
    }

    public function testThePluginPutsTheLabelsUnderTheProductName(): void
    {
        $this->resolved = [$this->label(1, 'email', 'text', null, 'Sale', '#b91c1c', '#ffffff')];
        $item = $this->orderItem();
        $block = $this->createStub(DefaultOrder::class);
        $block->method('getData')->willReturnCallback(static fn (string $key = '') => $key === 'item' ? $item : null);
        $plugin = new ItemLabelsPlugin($this->itemLabels());

        $html = $plugin->afterToHtml($block, '<td><p class="product-name">Bag</p><p class="sku">SKU: 1</p></td>');

        self::assertStringStartsWith('<td><p class="product-name">Bag</p><p class="openlabel-email"', $html);
        self::assertStringEndsWith('</p><p class="sku">SKU: 1</p></td>', $html);
        self::assertSame('<td>x</td>', $plugin->afterToHtml($block, '<td>x</td>'), 'a custom template without the name stays as it is');
    }

    private function itemLabels(): ItemLabels
    {
        $resolver = $this->createStub(LabelResolverInterface::class);
        $resolver->method('getForProducts')->willReturnCallback(function (array $ids, int $store, int $group): array {
            $this->calls[] = [$ids, $store, $group];

            return [42 => $this->resolved];
        });
        $renderer = $this->createStub(Renderer::class);
        $renderer->method('render')->willReturnCallback(
            static fn (string $text, $product, Context $context): Rendered => new Rendered('[' . $text . ']', [])
        );
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $config = $this->createStub(TypedReader::class);
        $config->method('getBool')->willReturnCallback(fn (): bool => $this->enabled);

        return new ItemLabels($resolver, $renderer, $storeManager, $config);
    }

    private function orderItem(): OrderItem
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(42);
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(3);
        $order->method('getCustomerGroupId')->willReturn(1);
        $item = $this->createStub(OrderItem::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getOrder')->willReturn($order);

        return $item;
    }

    private function label(int $id, string $area, string $type, ?string $shape, ?string $text, ?string $bg, ?string $fg): ResolvedLabel
    {
        $om = new ObjectManager($this);
        /** @var Placement $placement */
        $placement = $om->getObject(Placement::class);
        $placement->setData(['placement_id' => $id, 'area' => $area, 'position' => 'under_name']);
        /** @var Design $design */
        $design = $om->getObject(Design::class);
        $design->setData(['design_id' => $id, 'type' => $type, 'shape' => $shape, 'bg_color' => $bg, 'text_color' => $fg]);
        $design->setText($text);

        return new ResolvedLabel($id, 'Label ' . $id, $id, false, 42, null, $placement, $design);
    }
}
