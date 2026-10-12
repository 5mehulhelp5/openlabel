<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Variable;

use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableFixture;
use Iranimij\OpenLabel\Test\Integration\Helper\StockSetter;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(CategoryFixture::class, ['name' => 'OL Clothing'], 'clothing')]
#[DataFixture(CategoryFixture::class, ['name' => 'OL Jackets', 'parent_id' => '$clothing.id$'], 'jackets')]
#[DataFixture(CategoryFixture::class, ['name' => 'OL Hidden', 'parent_id' => '$jackets.id$', 'is_active' => false], 'hidden')]
#[DataFixture(
    ProductFixture::class,
    ['sku' => 'ol-var', 'price' => 100, 'special_price' => 80, 'category_ids' => ['$clothing.id$', '$jackets.id$', '$hidden.id$']],
    'var'
)]
#[DataFixture(AttributeFixture::class, as: 'attr')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-var-a', 'price' => 50, 'special_price' => 40, '$attr.attribute_code$' => '$attr.option_1$'], 'var_a')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-var-b', 'price' => 60, '$attr.attribute_code$' => '$attr.option_2$'], 'var_b')]
#[DataFixture(ConfigurableFixture::class, ['sku' => 'ol-var-conf', '_options' => ['$attr$'], '_links' => ['$var_a$', '$var_b$']], 'conf')]
#[DataFixture(GuestCartFixture::class, as: 'cart')]
#[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$var.id$', 'qty' => 3])]
#[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
class RendererTest extends TestCase
{
    private ?Renderer $renderer = null;
    private ?ProductRepositoryInterface $products = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->renderer = $om->get(Renderer::class);
        $this->products = $om->get(ProductRepositoryInterface::class);
        // 10 in stock before the fixture order of 3 is placed... the order already exists: set 7 and let the
        // reservation of 3 reduce the salable quantity to 4 (see the first test).
        $om->get(StockSetter::class)->apply([(int) DataFixtureStorageManager::getStorage()->get('var')->getId() => [7.0, true]]);
    }

    public function testPriceStockAndSkuVariablesOnARealProduct(): void
    {
        $product = $this->products->get('ol-var');

        $rendered = $this->renderer->render(
            'Save {SAVE_PERCENT}% ({SAVE_AMOUNT}) · {PRICE} → {FINAL_PRICE} · {STOCK_QTY} left · {SKU}',
            $product,
            $this->context()
        );

        self::assertSame('Save 20% ($20.00) · $100.00 → $80.00 · 4 left · ol-var', $rendered->getHtml(), '7 in stock minus the 3 ordered by the fixture: salable quantity');
    }

    public function testConfigurableUsesItsLowestFinalPrice(): void
    {
        $product = $this->products->get('ol-var-conf');

        $rendered = $this->renderer->render('from {FINAL_PRICE}, save {SAVE_PERCENT}%', $product, $this->context());

        self::assertSame('from $40.00, save 20%', $rendered->getHtml());
    }

    public function testSoldLast30DaysCountsOrderedQuantityAfterPreload(): void
    {
        $product = $this->products->get('ol-var');
        $this->renderer->preload([$product], $this->context());

        $rendered = $this->renderer->render('{SOLD_LAST_30D} sold', $product, $this->context());

        self::assertSame('3 sold', $rendered->getHtml());
    }

    public function testRatingAndReviewCountComeFromTheStoreSummary(): void
    {
        $product = $this->products->get('ol-var');
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName('review_entity_summary'), ['entity_pk_value = ?' => $product->getId()]);
        $connection->insert($resource->getTableName('review_entity_summary'), [
            'entity_pk_value' => $product->getId(), 'entity_type' => 1, 'reviews_count' => 4, 'rating_summary' => 86, 'store_id' => 1,
        ]);
        $this->renderer->preload([$product], $this->context());

        $rendered = $this->renderer->render('{RATING} stars from {REVIEW_COUNT} reviews', $product, $this->context());

        self::assertSame('4.3 stars from 4 reviews', $rendered->getHtml());
    }

    public function testCategoryNameIsTheProductsDeepestActiveCategory(): void
    {
        $product = $this->products->get('ol-var');
        $this->renderer->preload([$product], $this->context());

        $rendered = $this->renderer->render('New in {CATEGORY_NAME}', $product, $this->context());

        self::assertSame('New in OL Jackets', $rendered->getHtml(), 'deepest category wins, inactive ones are skipped');
    }

    public function testCategoryNameOnACategoryPageIsThatCategory(): void
    {
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $clothing = Bootstrap::getObjectManager()->get(CategoryRepositoryInterface::class)
            ->get((int) DataFixtureStorageManager::getStorage()->get('clothing')->getId(), 1);
        $registry->register('current_category', $clothing, true);
        try {
            $product = $this->products->get('ol-var');
            self::assertSame('OL Clothing', $this->renderer->render('{CATEGORY_NAME}', $product, $this->context())->getHtml());

            $registry->register('current_product', $product, true);
            self::assertSame(
                'OL Jackets',
                $this->renderer->render('{CATEGORY_NAME}', $product, $this->context())->getHtml(),
                'on a product page the product\'s own category is used, whatever category the shopper came from (page cache)'
            );
        } finally {
            $registry->unregister('current_category');
            $registry->unregister('current_product');
        }
    }

    public function testCategoryNameIsEmptyWithoutACategory(): void
    {
        $rendered = $this->renderer->render('{CATEGORY_NAME}', $this->products->get('ol-var-b'), $this->context());

        self::assertSame('', $rendered->getHtml());
        self::assertSame(['CATEGORY_NAME'], $rendered->getEmptyVariables());
    }

    public function testBrandReadsTheConfiguredAttribute(): void
    {
        $attribute = DataFixtureStorageManager::getStorage()->get('attr');
        $config = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $product = $this->products->get('ol-var-a');
        try {
            self::assertTrue($this->renderer->render('{BRAND}', $product, $this->context())->hasEmptyVariable(), 'no manufacturer set');

            $config->setValue('openlabel/variables/brand_attribute', $attribute->getAttributeCode(), 'store', 'default');
            $rendered = $this->renderer->render('by {BRAND}', $product, $this->context());

            self::assertSame('by ' . $product->getAttributeText($attribute->getAttributeCode()), $rendered->getHtml());
            self::assertFalse($rendered->hasEmptyVariable());
        } finally {
            $config->setValue('openlabel/variables/brand_attribute', 'manufacturer', 'store', 'default');
        }
    }

    private function context(): Context
    {
        return new Context(1, 1, 0);
    }
}
