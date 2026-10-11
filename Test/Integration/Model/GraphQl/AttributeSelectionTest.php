<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\GraphQl;

use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\AttributeProcessor;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL product queries load only the attributes the query asks for. Asking for `openlabel_labels` must load the
 * attributes the price variables read ({SAVE_PERCENT}, {PRICE}, {SPECIAL_PRICE}, {SPECIAL_ENDS_IN}…), or a
 * "Sale -{SAVE_PERCENT}%" label reads "Sale -%" in GraphQL while the storefront shows "Sale -25%".
 */
#[AppArea(Area::AREA_GRAPHQL)]
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-gql-price', 'price' => 32, 'special_price' => 24, 'special_to_date' => '2036-12-31 00:00:00'], 'product')]
class AttributeSelectionTest extends TestCase
{
    public function testOpenLabelLabelsLoadsThePriceAttributes(): void
    {
        $om = Bootstrap::getObjectManager();
        $collection = $om->get(CollectionFactory::class)->create();
        $collection->addIdFilter((int) DataFixtureStorageManager::getStorage()->get('product')->getId());

        $om->get(AttributeProcessor::class)->process(
            $collection,
            $om->get(SearchCriteriaBuilder::class)->create(),
            ['sku', 'openlabel_labels']
        );
        /** @var Product $product */
        $product = $collection->getFirstItem();

        self::assertEquals(32, (float) $product->getData('price'));
        self::assertEquals(24, (float) $product->getData('special_price'));
        self::assertNotEmpty($product->getData('special_to_date'));
    }
}
