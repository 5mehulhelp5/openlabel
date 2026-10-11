<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Condition\PriceRange;
use Iranimij\OpenLabel\Model\Condition\Rating;
use Iranimij\OpenLabel\Model\Condition\ReviewCount;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Model\Rule\RuleFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogRule\Test\Fixture\Rule as CatalogRuleFixture;
use Magento\Framework\App\ResourceConnection;
use Iranimij\OpenLabel\Test\Integration\Helper\StockSetter;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use PHPUnit\Framework\TestCase;

/**
 * Every built-in condition and the native attribute condition must select the right products in SQL,
 * because the indexer never evaluates rules in PHP (06 · F4).
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-full', 'price' => 100, 'name' => 'Full price jacket'], 'full')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-sale20', 'price' => 100, 'special_price' => 80, 'name' => 'Sale jacket'], 'sale20')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-sale5', 'price' => 100, 'special_price' => 95], 'sale5')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-rule30', 'price' => 100], 'rule30')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-out', 'price' => 20], 'out')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-low', 'price' => 20], 'low')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-old', 'price' => 20, 'news_from_date' => '2020-01-01 00:00:00', 'news_to_date' => '2020-02-01 00:00:00'], 'old')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-new', 'price' => 20, 'news_from_date' => '2020-01-01 00:00:00', 'news_to_date' => null], 'new')]
#[DataFixture(CatalogRuleFixture::class, ['name' => 'ol wholesale 30', 'website_ids' => [1], 'customer_group_ids' => [2], 'discount_amount' => 30, 'conditions' => [['attribute' => 'sku', 'operator' => '==', 'value' => 'ol-rule30']]], 'catalogrule')]
class ConditionSqlTest extends TestCase
{
    private const ALL = ['ol-full', 'ol-sale20', 'ol-sale5', 'ol-rule30', 'ol-out', 'ol-low', 'ol-old', 'ol-new'];

    /**
     * Fixtures are re-created for every test, so the extra data is prepared in every setUp.
     */
    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $resource = $om->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $ids = $this->ids(['ol-old', 'ol-sale20', 'ol-full']);
        // "days since created" and review summaries are not fixture-able; set them directly.
        $connection->update(
            $resource->getTableName('catalog_product_entity'),
            ['created_at' => '2020-01-01 00:00:00'],
            ['entity_id = ?' => $ids['ol-old']]
        );
        $summary = $resource->getTableName('review_entity_summary');
        $connection->delete($summary, ['entity_pk_value IN (?)' => array_values($ids)]);
        $connection->insertMultiple($summary, [
            ['entity_pk_value' => $ids['ol-sale20'], 'entity_type' => 1, 'reviews_count' => 5, 'rating_summary' => 90, 'store_id' => 1],
            ['entity_pk_value' => $ids['ol-full'], 'entity_type' => 1, 'reviews_count' => 1, 'rating_summary' => 60, 'store_id' => 1],
        ]);
        // German name on the default store view only (store-scoped attribute, 02 · Architecture §2).
        $repository = $om->get(ProductRepositoryInterface::class);
        $product = $repository->get('ol-sale20', true, 1);
        $product->setName('Angebot Jacke');
        $repository->save($product);
        $stockIds = $this->ids(['ol-out', 'ol-low']);
        $om->get(StockSetter::class)->apply([$stockIds['ol-out'] => [0.0, false], $stockIds['ol-low'] => [3.0, true]]);
    }

    /**
     * Quick switches sit at the root and the advanced tree is a nested group (QuickConditions); the core rule SQL
     * builder dropped the switches' joins when it recursed into the group ("Unknown column ol_price.price").
     */
    public function testQuickSwitchesKeepTheirJoinsNextToANestedAdvancedTree(): void
    {
        $nested = ['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
            ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '{}', 'value' => 'sale'],
        ]];

        self::assertSame(['ol-sale20'], $this->match([$this->builtIn(OnSale::class, 'discount_percent', '>=', '10'), $nested]));
        self::assertSame(['ol-sale20'], $this->match([$nested, $this->builtIn(OnSale::class, 'discount_percent', '>=', '10')]));
    }

    public function testSeveralSwitchesWithTheirOwnJoinsAroundNestedGroups(): void
    {
        $deep = ['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
            ['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '{}', 'value' => 'ol-'],
            ]],
        ]];

        self::assertSame(['ol-sale20'], $this->match([
            $this->builtIn(OnSale::class, 'discount_percent', '>=', '10'),
            $deep,
            $this->builtIn(Stock::class, 'is_salable', '==', '1'),
            $this->builtIn(Rating::class, 'rating', '>=', '4'),
        ], group: 0));
    }

    public function testOnSaleFindsSpecialPriceProductsForEveryGroup(): void
    {
        self::assertSame(['ol-sale20', 'ol-sale5'], $this->match($this->builtIn(OnSale::class, 'on_sale', '==', '1'), group: 0));
        self::assertSame(['ol-sale20'], $this->match($this->builtIn(OnSale::class, 'discount_percent', '>=', '10'), group: 0));
        self::assertSame(['ol-sale20'], $this->match($this->builtIn(OnSale::class, 'discount_amount', '>=', '20'), group: 0));
    }

    public function testCatalogPriceRuleCountsAsOnSaleOnlyForItsGroup(): void
    {
        $condition = $this->builtIn(OnSale::class, 'discount_percent', '>=', '25');

        self::assertSame([], $this->match($condition, group: 0));
        self::assertSame(['ol-rule30'], $this->match($condition, group: 2), 'wholesale sees the 30 % rule');
    }

    public function testPriceRangeUsesTheGroupFinalPrice(): void
    {
        $condition = $this->builtIn(PriceRange::class, 'final_price', '<', '85');

        // Whether the price index keeps out-of-stock products differs between Magento versions: ignore ol-out.
        self::assertSame(['ol-sale20', 'ol-low', 'ol-old', 'ol-new'], $this->withoutOut($this->match($condition, group: 0)));
        self::assertSame(['ol-sale20', 'ol-rule30', 'ol-low', 'ol-old', 'ol-new'], $this->withoutOut($this->match($condition, group: 2)));
    }

    public function testStockConditionsUseSalableQuantity(): void
    {
        self::assertSame(['ol-out'], $this->match($this->builtIn(Stock::class, 'is_salable', '==', '0')));
        self::assertSame(['ol-out', 'ol-low'], $this->match($this->builtIn(Stock::class, 'salable_qty', '<=', '5')));
    }

    public function testIsNewConditions(): void
    {
        self::assertSame(['ol-new'], $this->match($this->builtIn(IsNew::class, 'news_dates', '==', '1')));
        $fresh = array_values(array_diff(self::ALL, ['ol-old']));
        self::assertSame($fresh, $this->match($this->builtIn(IsNew::class, 'days_since_created', '<=', '30')));
    }

    public function testReviewConditions(): void
    {
        self::assertSame(['ol-sale20'], $this->match($this->builtIn(Rating::class, 'rating', '>=', '4')));
        self::assertSame(['ol-full', 'ol-sale20'], $this->match($this->builtIn(ReviewCount::class, 'review_count', '>=', '1')));
    }

    public function testNativeAttributeConditionIsStoreScoped(): void
    {
        $condition = ['type' => ProductCondition::class, 'attribute' => 'name', 'operator' => '{}', 'value' => 'Angebot'];

        self::assertSame(['ol-sale20'], $this->match($condition, store: 1));
        self::assertSame([], $this->match($condition, store: 0));
    }

    public function testConditionsCombineWithAllAndAny(): void
    {
        $onSale = $this->builtIn(OnSale::class, 'on_sale', '==', '1');
        $cheap = $this->builtIn(PriceRange::class, 'final_price', '<', '50');

        self::assertSame([], $this->match([$onSale, $cheap], aggregator: 'all', group: 0));
        self::assertSame(['ol-sale20', 'ol-sale5', 'ol-low', 'ol-old', 'ol-new'], $this->withoutOut($this->match([$onSale, $cheap], aggregator: 'any', group: 0)));
    }

    public function testRuleKnowsWhenItNeedsCustomerGroupRows(): void
    {
        $rule = $this->rule([$this->builtIn(Stock::class, 'is_salable', '==', '1')]);
        self::assertFalse($rule->requiresCustomerGroup());

        $rule = $this->rule([$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        self::assertTrue($rule->requiresCustomerGroup());
    }

    /**
     * @param string[] $skus
     * @return string[]
     */
    private function withoutOut(array $skus): array
    {
        return array_values(array_diff($skus, ['ol-out']));
    }

    /**
     * @return array<string, mixed>
     */
    private function builtIn(string $class, string $attribute, string $operator, string $value): array
    {
        return ['type' => $class, 'attribute' => $attribute, 'operator' => $operator, 'value' => $value];
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $conditions one condition or a list
     * @return string[] matched SKUs in fixture order
     */
    private function match(array $conditions, string $aggregator = 'all', int $store = 1, int $group = 0): array
    {
        $om = Bootstrap::getObjectManager();
        $om->get(StoreManagerInterface::class)->setCurrentStore($store);
        $rule = $this->rule(isset($conditions['type']) ? [$conditions] : $conditions, $aggregator);
        $rule->setStoreId($store)->setWebsiteId(1)->setCustomerGroupId($group);

        $collection = $om->create(CollectionFactory::class)->create();
        $collection->addAttributeToFilter('sku', ['like' => 'ol-%']);
        $rule->applyToCollection($collection);
        $skus = $collection->getColumnValues('sku');
        $om->get(StoreManagerInterface::class)->setCurrentStore(1);

        return array_values(array_intersect(self::ALL, $skus));
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     */
    private function rule(array $conditions, string $aggregator = 'all'): \Iranimij\OpenLabel\Model\Rule\Rule
    {
        $om = Bootstrap::getObjectManager();
        $tree = ['type' => Combine::class, 'aggregator' => $aggregator, 'value' => '1', 'conditions' => $conditions];
        $rule = $om->get(RuleFactory::class)->create();
        $rule->setConditionsSerialized($om->get(Json::class)->serialize($tree));

        return $rule;
    }

    /**
     * @param string[] $skus
     * @return array<string, int>
     */
    private function ids(array $skus): array
    {
        $ids = [];
        foreach ($skus as $sku) {
            $ids[$sku] = (int) DataFixtureStorageManager::getStorage()->get(substr($sku, 3))->getId();
        }

        return $ids;
    }
}
