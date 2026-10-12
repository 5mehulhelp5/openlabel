<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Email;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\OrderRepositoryInterface;
use Iranimij\OpenLabel\Plugin\Email\ItemLabelsPlugin;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListInterface;
use Magento\Sales\Block\Order\Email\Items\DefaultItems;
use Magento\Sales\Block\Order\Email\Items\Order\DefaultOrder;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Labels in order emails (1.1): the core email item template gets the item's "Order emails" labels under its name.
 */
#[AppArea('frontend')]
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-email', 'price' => 30], 'product')]
#[DataFixture(GuestCartFixture::class, as: 'cart')]
#[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1])]
#[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
#[DataFixture(DesignFixture::class, ['bg_color' => '#166534', 'text_color' => '#ffffff', 'store_texts' => [0 => ['text' => 'Eco <b>pick</b>']]], 'design')]
class ItemLabelsTest extends TestCase
{
    private ?int $labelId = null;

    protected function setUp(): void
    {
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply([
            'name' => 'Email label',
            'design_id' => (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId(),
            'conditions_serialized' => json_encode([
                'type' => Combine::class, 'aggregator' => 'all', 'value' => '1',
                'conditions' => [[
                    'type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'ol-email',
                ]],
            ]),
            'placements' => [['area' => 'email', 'position' => 'under_name'], ['area' => 'listing', 'position' => 'tl']],
        ]);
        $this->labelId = (int) $label->getLabelId();
    }

    protected function tearDown(): void
    {
        if ($this->labelId !== null) {
            Bootstrap::getObjectManager()->get(LabelRepositoryInterface::class)->deleteById($this->labelId);
        }
    }

    public function testTheOrderEmailShowsTheLabelUnderTheItemName(): void
    {
        $om = Bootstrap::getObjectManager();
        $order = $om->get(OrderRepositoryInterface::class)->get((int) DataFixtureStorageManager::getStorage()->get('order')->getId());
        $item = array_values($order->getItems())[0];
        $item->setOrder($order);
        $block = $om->get(LayoutInterface::class)->createBlock(DefaultOrder::class)->setData('item', $item);

        // The markup of Magento_Sales::email/items/order/default.phtml around the name.
        $html = (string) $om->get(ItemLabelsPlugin::class)->afterToHtml(
            $block,
            '<tr><td class="item-info"><p class="product-name">' . $item->getName() . '</p><p class="sku">SKU: ol-email</p></td></tr>'
        );

        self::assertMatchesRegularExpression(
            '#<p class="product-name">[^<]*</p><p class="openlabel-email" style="margin:4px 0 0;"><span style="[^"]*background:\#166534;color:\#ffffff;[^"]*">Eco <b>pick</b></span></p>#',
            $html
        );
        self::assertSame(1, substr_count($html, 'openlabel-email'), 'only the email placement');
    }

    public function testThePluginIsOnEveryEmailItemRenderer(): void
    {
        $plugins = Bootstrap::getObjectManager()->get(PluginListInterface::class);

        foreach ([DefaultOrder::class, DefaultItems::class] as $type) {
            $next = $plugins->getNext($type, 'toHtml');
            self::assertContains('openlabel_email_item_labels', $next[DefinitionInterface::LISTENER_AFTER] ?? [], $type);
        }
    }
}
