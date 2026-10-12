<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\Bestseller;
use Iranimij\OpenLabel\Model\Condition\FreeShipping;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\MostViewed;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Condition\PriceRange;
use Iranimij\OpenLabel\Model\Condition\Rating;
use Iranimij\OpenLabel\Model\Condition\ReviewCount;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Indexer\Matcher;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Model\Rule\Rule;
use Iranimij\OpenLabel\Model\Rule\RuleFactory;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Iranimij\OpenLabel\Test\Integration\Helper\StockSetter;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Every condition with every operator it offers, through the indexer's Matcher (the production path).
 *
 * Four products with known values. The expected result is computed from those values and the documented rules
 * (docs/engine.md): missing data (no price index row, no rating, no stock row) never matches, whatever the operator;
 * a product without reviews has 0 reviews; quantity conditions apply to simple products.
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(CategoryFixture::class, as: 'cat')]
#[DataFixture(AttributeFixture::class, as: 'attr')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-op-a', 'price' => 100, 'special_price' => 80, 'category_ids' => ['$cat.id$'], '$attr.attribute_code$' => '$attr.option_1$', 'news_from_date' => '2020-01-01 00:00:00', 'news_to_date' => '2099-01-01 00:00:00'], 'a')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-op-b', 'price' => 50, '$attr.attribute_code$' => '$attr.option_2$'], 'b')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-op-c', 'price' => 200, 'special_price' => 150, 'category_ids' => ['$cat.id$'], 'news_from_date' => '2020-01-01 00:00:00', 'news_to_date' => '2020-02-01 00:00:00'], 'c')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-op-d', 'price' => 30], 'd')]
class OperatorMatrixTest extends TestCase
{
    private const NUMERIC = ['==', '!=', '>=', '>', '<=', '<', '()', '!()'];
    private const BOOLEAN = ['==', '!='];

    /** Values of each product; null = the product has no such data. */
    private const VALUES = [
        'discount_percent' => ['ol-op-a' => 20, 'ol-op-b' => 0, 'ol-op-c' => 25],
        'discount_amount' => ['ol-op-a' => 20, 'ol-op-b' => 0, 'ol-op-c' => 50],
        'on_sale' => ['ol-op-a' => 1, 'ol-op-b' => 0, 'ol-op-c' => 1],
        'final_price' => ['ol-op-a' => 80, 'ol-op-b' => 50, 'ol-op-c' => 150],
        'price' => ['ol-op-a' => 100, 'ol-op-b' => 50, 'ol-op-c' => 200],
        'days_since_created' => ['ol-op-a' => 10, 'ol-op-b' => 40, 'ol-op-c' => 100, 'ol-op-d' => 40],
        'days_since_updated' => ['ol-op-a' => 10, 'ol-op-b' => 2, 'ol-op-c' => 100, 'ol-op-d' => 40],
        'news_dates' => ['ol-op-a' => 1, 'ol-op-b' => 0, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'is_salable' => ['ol-op-a' => 1, 'ol-op-b' => 1, 'ol-op-c' => 1, 'ol-op-d' => 0],
        'salable_qty' => ['ol-op-a' => 3, 'ol-op-b' => 7, 'ol-op-c' => 100, 'ol-op-d' => 0],
        'rating' => ['ol-op-a' => 4.5, 'ol-op-b' => 3, 'ol-op-c' => null, 'ol-op-d' => null],
        'review_count' => ['ol-op-a' => 5, 'ol-op-b' => 1, 'ol-op-c' => 0, 'ol-op-d' => 0],
        // Sales: a 5 units 3 days ago, b 2 units 20 days ago + 10 units 60 days ago, d 1 unit in a canceled order.
        'sold_last_7d' => ['ol-op-a' => 5, 'ol-op-b' => 0, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'sold_last_30d' => ['ol-op-a' => 5, 'ol-op-b' => 2, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'sold_last_90d' => ['ol-op-a' => 5, 'ol-op-b' => 12, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'sales_rank_30d' => ['ol-op-a' => 1, 'ol-op-b' => 2, 'ol-op-c' => null, 'ol-op-d' => null],
        // Product views: a once, b three times (all this week), d once 40 days ago.
        'views_last_7d' => ['ol-op-a' => 1, 'ol-op-b' => 3, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'views_last_30d' => ['ol-op-a' => 1, 'ol-op-b' => 3, 'ol-op-c' => 0, 'ol-op-d' => 0],
        'view_rank_30d' => ['ol-op-a' => 2, 'ol-op-b' => 1, 'ol-op-c' => null, 'ol-op-d' => null],
        // Free-shipping threshold 100 on the final price.
        'free_shipping' => ['ol-op-a' => 0, 'ol-op-b' => 0, 'ol-op-c' => 1],
    ];

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $resource = $om->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $ids = $this->ids();
        $om->get(StockSetter::class)->apply([
            $ids['ol-op-a'] => [3.0, true],
            $ids['ol-op-b'] => [7.0, true],
            $ids['ol-op-c'] => [100.0, true],
            $ids['ol-op-d'] => [0.0, false],
        ]);
        $summary = $resource->getTableName('review_entity_summary');
        $connection->delete($summary, ['entity_pk_value IN (?)' => array_values($ids)]);
        $connection->insertMultiple($summary, [
            ['entity_pk_value' => $ids['ol-op-a'], 'entity_type' => 1, 'reviews_count' => 5, 'rating_summary' => 90, 'store_id' => 1],
            ['entity_pk_value' => $ids['ol-op-b'], 'entity_type' => 1, 'reviews_count' => 1, 'rating_summary' => 60, 'store_id' => 1],
        ]);
        $this->addOrders($ids);
        $this->addViews($ids);
        // Last, so no save touches them again: days since created / updated.
        foreach ([
            'ol-op-a' => [10, 10],
            'ol-op-b' => [40, 2],
            'ol-op-c' => [100, 100],
            'ol-op-d' => [40, 40],
        ] as $sku => [$created, $updated]) {
            $connection->update(
                $resource->getTableName('catalog_product_entity'),
                [
                    'created_at' => new \Zend_Db_Expr(sprintf('DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $created)),
                    'updated_at' => new \Zend_Db_Expr(sprintf('DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $updated)),
                ],
                ['entity_id = ?' => $ids[$sku]]
            );
        }
    }

    public function testOnSale(): void
    {
        // Out-of-stock products are in the price index on some Magento versions only: price checks use A to C.
        $this->assertBoolean(OnSale::class, 'on_sale', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
        $this->assertNumeric(OnSale::class, 'discount_percent', '20', '0, 25', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
        $this->assertNumeric(OnSale::class, 'discount_amount', '20', '0, 50', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
    }

    public function testPriceRange(): void
    {
        $this->assertNumeric(PriceRange::class, 'final_price', '80', '50, 150', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
        $this->assertNumeric(PriceRange::class, 'price', '100', '50, 200', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
    }

    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $ids = array_values($this->ids());
        $orderIds = $connection->fetchCol(
            $connection->select()->from($resource->getTableName('sales_order_item'), ['order_id'])
                ->where('product_id IN (?)', $ids)
        );
        if ($orderIds !== []) {
            $connection->delete($resource->getTableName('sales_order'), ['entity_id IN (?)' => $orderIds]);
        }
        $connection->delete($resource->getTableName('report_event'), ['object_id IN (?)' => $ids, 'event_type_id = ?' => 1]);
        $connection->delete($resource->getTableName('salesrule'), ['name = ?' => 'ol-op free shipping']);
    }

    public function testIsNew(): void
    {
        $this->assertBoolean(IsNew::class, 'news_dates');
        $this->assertNumeric(IsNew::class, 'days_since_created', '40', '10, 100');
        $this->assertNumeric(IsNew::class, 'days_since_updated', '10', '2, 40');
    }

    public function testStock(): void
    {
        $this->assertBoolean(Stock::class, 'is_salable');
        $this->assertNumeric(Stock::class, 'salable_qty', '7', '0, 3');
    }

    public function testRatingAndReviewCount(): void
    {
        $this->assertNumeric(Rating::class, 'rating', '3', '3, 4.5');
        $this->assertNumeric(ReviewCount::class, 'review_count', '1', '0, 5');
    }

    public function testBestseller(): void
    {
        $this->assertNumeric(Bestseller::class, 'sold_last_7d', '5', '0, 5');
        $this->assertNumeric(Bestseller::class, 'sold_last_30d', '2', '0, 5');
        $this->assertNumeric(Bestseller::class, 'sold_last_90d', '12', '5, 12');
        $this->assertNumeric(Bestseller::class, 'sales_rank_30d', '1', '1, 2');
    }

    public function testMostViewed(): void
    {
        $this->assertNumeric(MostViewed::class, 'views_last_7d', '1', '0, 3');
        $this->assertNumeric(MostViewed::class, 'views_last_30d', '3', '1, 3');
        $this->assertNumeric(MostViewed::class, 'view_rank_30d', '2', '1');
    }

    #[Config('carriers/freeshipping/active', '1', 'store', 'default')]
    #[Config('carriers/freeshipping/free_shipping_subtotal', '100', 'store', 'default')]
    public function testFreeShipping(): void
    {
        // Out-of-stock d: the price index keeps it on 2.4.8+ only, so its answer differs by version.
        $this->assertBoolean(FreeShipping::class, 'free_shipping', ['ol-op-a', 'ol-op-b', 'ol-op-c']);
    }

    #[Config('carriers/freeshipping/active', '0', 'store', 'default')]
    #[Config('carriers/freeshipping/free_shipping_subtotal', '100', 'store', 'default')]
    public function testFreeShippingNeedsTheCarrier(): void
    {
        $universe = ['ol-op-a', 'ol-op-b', 'ol-op-c'];
        self::assertSame([], $this->match($this->builtIn(FreeShipping::class, 'free_shipping', '==', '1'), $universe));
        self::assertSame($universe, $this->match($this->builtIn(FreeShipping::class, 'free_shipping', '==', '0'), $universe));
    }

    public function testFreeShippingFromACartRuleForEveryone(): void
    {
        $this->addFreeShippingCartRule();
        $universe = ['ol-op-a', 'ol-op-b', 'ol-op-c'];

        self::assertSame($universe, $this->match($this->builtIn(FreeShipping::class, 'free_shipping', '==', '1'), $universe));
    }

    public function testBuiltInsOfferTheseOperators(): void
    {
        foreach ([
            [OnSale::class, 'on_sale', self::BOOLEAN],
            [OnSale::class, 'discount_percent', self::NUMERIC],
            [IsNew::class, 'news_dates', self::BOOLEAN],
            [IsNew::class, 'days_since_created', self::NUMERIC],
            [Stock::class, 'is_salable', self::BOOLEAN],
            [Stock::class, 'salable_qty', self::NUMERIC],
            [Rating::class, 'rating', self::NUMERIC],
            [ReviewCount::class, 'review_count', self::NUMERIC],
            [PriceRange::class, 'final_price', self::NUMERIC],
            [Bestseller::class, 'sales_rank_30d', self::NUMERIC],
            [MostViewed::class, 'views_last_30d', self::NUMERIC],
            [FreeShipping::class, 'free_shipping', self::BOOLEAN],
        ] as [$class, $attribute, $operators]) {
            $condition = Bootstrap::getObjectManager()->create($class);
            $condition->setAttribute($attribute);
            self::assertSame($operators, $condition->getDefaultOperatorInputByType()[$condition->getInputType()], $class . ' ' . $attribute);
        }
    }

    public function testCategoryCondition(): void
    {
        $category = (string) DataFixtureStorageManager::getStorage()->get('cat')->getId();
        $all = ['ol-op-a', 'ol-op-b', 'ol-op-c', 'ol-op-d'];
        foreach ([
            ['==', $category, ['ol-op-a', 'ol-op-c']],
            ['!=', $category, ['ol-op-b', 'ol-op-d']],
            ['()', $category . ', 999999', ['ol-op-a', 'ol-op-c']],
            ['!()', $category . ', 999999', ['ol-op-b', 'ol-op-d']],
        ] as [$operator, $value, $expected]) {
            self::assertSame($expected, $this->match($this->native('category_ids', $operator, $value), $all), 'category ' . $operator);
        }
    }

    public function testTextNumberSelectAndDateAttributes(): void
    {
        $all = ['ol-op-a', 'ol-op-b', 'ol-op-c', 'ol-op-d'];
        $attribute = DataFixtureStorageManager::getStorage()->get('attr');
        $code = (string) $attribute->getAttributeCode();
        $option = (string) $attribute->getData('option_1');
        foreach ([
            ['sku', '==', 'ol-op-b', ['ol-op-b']],
            ['sku', '!=', 'ol-op-b', ['ol-op-a', 'ol-op-c', 'ol-op-d']],
            ['sku', '{}', 'op-c', ['ol-op-c']],
            ['sku', '!{}', 'op-c', ['ol-op-a', 'ol-op-b', 'ol-op-d']],
            ['sku', '()', 'ol-op-a, ol-op-d', ['ol-op-a', 'ol-op-d']],
            ['sku', '!()', 'ol-op-a, ol-op-d', ['ol-op-b', 'ol-op-c']],
            ['price', '==', '100', ['ol-op-a']],
            ['price', '!=', '100', ['ol-op-b', 'ol-op-c', 'ol-op-d']],
            ['price', '>=', '100', ['ol-op-a', 'ol-op-c']],
            ['price', '>', '100', ['ol-op-c']],
            ['price', '<=', '50', ['ol-op-b', 'ol-op-d']],
            ['price', '<', '50', ['ol-op-d']],
            [$code, '==', $option, ['ol-op-a']],
            [$code, '!=', $option, ['ol-op-b']],
            [$code, '()', $option, ['ol-op-a']],
            [$code, '!()', $option, ['ol-op-b']],
            ['news_from_date', '==', '2020-01-01', ['ol-op-a', 'ol-op-c']],
            ['news_from_date', '>=', '2019-12-31', ['ol-op-a', 'ol-op-c']],
            ['news_from_date', '<=', '2020-01-01', ['ol-op-a', 'ol-op-c']],
        ] as [$field, $operator, $value, $expected]) {
            self::assertSame($expected, $this->match($this->native($field, $operator, $value), $all), $field . ' ' . $operator . ' ' . $value);
        }
    }

    /**
     * @param array<string, int> $ids sku => product id
     */
    private function addOrders(array $ids): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        foreach ([
            ['ol-op-a', 5, 3, 'complete'],
            ['ol-op-b', 2, 20, 'processing'],
            ['ol-op-b', 10, 60, 'complete'],
            ['ol-op-d', 1, 2, 'canceled'],
        ] as [$sku, $qty, $daysAgo, $state]) {
            $created = new \Zend_Db_Expr(sprintf('DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $daysAgo));
            $connection->insert($resource->getTableName('sales_order'), [
                'state' => $state, 'status' => $state, 'store_id' => 1, 'created_at' => $created,
            ]);
            $orderId = (int) $connection->lastInsertId($resource->getTableName('sales_order'));
            $connection->insert($resource->getTableName('sales_order_item'), [
                'order_id' => $orderId, 'store_id' => 1, 'product_id' => $ids[$sku], 'sku' => $sku,
                'product_type' => 'simple', 'qty_ordered' => $qty, 'created_at' => $created,
            ]);
        }
    }

    /**
     * @param array<string, int> $ids sku => product id
     */
    private function addViews(array $ids): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $rows = [];
        foreach ([['ol-op-a', 1], ['ol-op-b', 2], ['ol-op-b', 3], ['ol-op-b', 4], ['ol-op-d', 40]] as [$sku, $daysAgo]) {
            $rows[] = [
                'logged_at' => new \Zend_Db_Expr(sprintf('DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $daysAgo)),
                'event_type_id' => 1, 'object_id' => $ids[$sku], 'subject_id' => 1, 'subtype' => 1, 'store_id' => 1,
            ];
        }
        foreach ($rows as $row) {
            $resource->getConnection()->insert($resource->getTableName('report_event'), $row);
        }
    }

    /**
     * An active cart rule without coupon and without conditions that gives free shipping to every group.
     */
    private function addFreeShippingCartRule(): void
    {
        $om = Bootstrap::getObjectManager();
        $rule = $om->create(\Magento\SalesRule\Model\Rule::class);
        $rule->setName('ol-op free shipping')->setIsActive(1)->setWebsiteIds([1])
            ->setCustomerGroupIds([0, 1, 2, 3])->setCouponType(\Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleFreeShipping(\Magento\OfflineShipping\Model\SalesRule\Rule::FREE_SHIPPING_ADDRESS)
            ->setSimpleAction('by_percent')->setDiscountAmount(0)->setStopRulesProcessing(0);
        $rule->save();
    }

    /**
     * @param string $class
     * @param string $attribute
     * @param string[] $universe
     */
    private function assertBoolean(string $class, string $attribute, array $universe = ['ol-op-a', 'ol-op-b', 'ol-op-c', 'ol-op-d']): void
    {
        foreach (self::BOOLEAN as $operator) {
            foreach (['1', '0'] as $value) {
                $expected = $this->expected($attribute, $operator, $value, $universe);
                $actual = $this->match($this->builtIn($class, $attribute, $operator, $value), $universe);
                self::assertSame($expected, $actual, sprintf('%s %s %s', $attribute, $operator, $value));
            }
        }
    }

    /**
     * @param string $class
     * @param string $attribute
     * @param string $value operand for the comparisons
     * @param string $list operand for "is one of"
     * @param string[] $universe
     */
    private function assertNumeric(string $class, string $attribute, string $value, string $list, array $universe = ['ol-op-a', 'ol-op-b', 'ol-op-c', 'ol-op-d']): void
    {
        foreach (self::NUMERIC as $operator) {
            $operand = in_array($operator, ['()', '!()'], true) ? $list : $value;
            $expected = $this->expected($attribute, $operator, $operand, $universe);
            $actual = $this->match($this->builtIn($class, $attribute, $operator, $operand), $universe);
            self::assertSame($expected, $actual, sprintf('%s %s %s', $attribute, $operator, $operand));
        }
    }

    /**
     * The documented semantics, independent of the SQL: missing data never matches.
     *
     * @param string[] $universe
     * @return string[]
     */
    private function expected(string $attribute, string $operator, string $operand, array $universe): array
    {
        $list = array_map('floatval', preg_split('#\s*,\s*#', $operand) ?: []);
        $matches = [];
        foreach ($universe as $sku) {
            $value = self::VALUES[$attribute][$sku] ?? null;
            if ($value === null) {
                continue;
            }
            $value = (float) $value;
            $number = (float) $operand;
            $hit = match ($operator) {
                '==' => $value == $number,
                '!=' => $value != $number,
                '>=' => $value >= $number,
                '>' => $value > $number,
                '<=' => $value <= $number,
                '<' => $value < $number,
                '()' => in_array($value, $list, false),
                '!()' => !in_array($value, $list, false),
            };
            if ($hit) {
                $matches[] = $sku;
            }
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $condition
     * @param string[] $universe
     * @return string[] matching SKUs in universe order
     */
    private function match(array $condition, array $universe): array
    {
        $om = Bootstrap::getObjectManager();
        /** @var Rule $rule */
        $rule = $om->get(RuleFactory::class)->create();
        $rule->setConditionsSerialized($om->get(Json::class)->serialize([
            'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [$condition],
        ]));
        $ids = $this->ids();
        $matched = $om->get(Matcher::class)->match($rule, 1, 0, array_map(static fn (string $sku): int => $ids[$sku], $universe));

        return array_values(array_filter($universe, static fn (string $sku): bool => in_array($ids[$sku], $matched, true)));
    }

    /**
     * @return array<string, mixed>
     */
    private function builtIn(string $class, string $attribute, string $operator, string $value): array
    {
        return ['type' => $class, 'attribute' => $attribute, 'operator' => $operator, 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function native(string $attribute, string $operator, string $value): array
    {
        return ['type' => ProductCondition::class, 'attribute' => $attribute, 'operator' => $operator, 'value' => $value];
    }

    /**
     * @return array<string, int>
     */
    private function ids(): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        return array_map('intval', $connection->fetchPairs(
            $connection->select()->from($resource->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
                ->where('sku IN (?)', ['ol-op-a', 'ol-op-b', 'ol-op-c', 'ol-op-d'])
        ));
    }
}
