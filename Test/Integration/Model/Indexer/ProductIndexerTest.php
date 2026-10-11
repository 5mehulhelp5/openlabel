<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Indexer;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Indexer\Product as ProductIndexer;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Magento\Bundle\Test\Fixture\Option as BundleOptionFixture;
use Magento\Bundle\Test\Fixture\Product as BundleProductFixture;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableFixture;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\CacheContext;
use Iranimij\OpenLabel\Test\Integration\Helper\StockSetter;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GroupedProduct\Test\Fixture\Product as GroupedFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use PHPUnit\Framework\TestCase;

/**
 * The index must hold exactly the right (label, product, store, group) rows for simple, configurable, grouped and
 * bundle products, price labels per customer group and store-scoped conditions per store (M1 "done when").
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(StoreFixture::class, ['code' => 'ol_de'], 'store_de')]
#[DataFixture(AttributeFixture::class, as: 'attr')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-full', 'price' => 100, 'name' => 'Full price'], 'full')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-sale20', 'price' => 100, 'special_price' => 80, 'name' => 'Sale item'], 'sale20')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-low', 'price' => 20], 'low')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-conf-a', 'price' => 100, 'special_price' => 70, '$attr.attribute_code$' => '$attr.option_1$'], 'conf_a')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-conf-b', 'price' => 100, '$attr.attribute_code$' => '$attr.option_2$'], 'conf_b')]
#[DataFixture(ConfigurableFixture::class, ['sku' => 'ol-conf', '_options' => ['$attr$'], '_links' => ['$conf_a$', '$conf_b$']], 'conf')]
#[DataFixture(GroupedFixture::class, ['sku' => 'ol-grp', 'product_links' => [['sku' => 'ol-full'], ['sku' => 'ol-sale20']]], 'grp')]
#[DataFixture(BundleOptionFixture::class, ['product_links' => ['$sale20$']], 'bundle_opt')]
#[DataFixture(BundleProductFixture::class, ['sku' => 'ol-bun', '_options' => ['$bundle_opt$']], 'bun')]
#[DataFixture(DesignFixture::class, [], 'design')]
class ProductIndexerTest extends TestCase
{
    private const GROUPS = [0, 1, 2, 3];

    private ?ProductIndexer $indexer = null;
    private ?LabelRepositoryInterface $labels = null;
    private ?ResourceConnection $resource = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->indexer = $om->get(ProductIndexer::class);
        $this->labels = $om->get(LabelRepositoryInterface::class);
        $this->resource = $om->get(ResourceConnection::class);
        $om->get(StockSetter::class)->apply([$this->id('low') => [3.0, true]]);
        $om->get(CacheContext::class)->flush();
    }

    public function testFullReindexWritesChildRowsPerGroupForPriceLabelsAndOneRowOtherwise(): void
    {
        $sale = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        $low = $this->label('Low', [$this->builtIn(Stock::class, 'salable_qty', '<=', '5')]);

        $this->indexer->executeFull();

        $saleRows = $this->rows($sale);
        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($saleRows, 1, 0));
        self::assertSame(self::GROUPS, $this->groupsOf($saleRows, 'ol-sale20', 1), 'price labels: one row per customer group');
        self::assertSame([-1], $this->groupsOf($this->rows($low), 'ol-low', 1), 'stock labels: one row with -1 = all groups');
        self::assertSame(['ol-low'], $this->skus($this->rows($low), 1, -1));
        self::assertSame([], $this->skus($this->rows($low), 0, -1), 'admin store gets no rows');
    }

    public function testLabelCustomerGroupsAndStoresLimitTheRows(): void
    {
        $label = $this->label('Wholesale sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')], [
            'customer_group_ids' => [2],
            'store_ids' => [1],
        ]);

        $this->indexer->executeFull();

        $rows = $this->rows($label);
        self::assertSame([2], $this->groupsOf($rows, 'ol-sale20', 1));
        self::assertSame([], $this->skus($rows, $this->storeDe(), 2), 'label limited to store 1');
    }

    public function testStoreScopedConditionsWriteDifferentRowsPerStore(): void
    {
        $repository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $product = $repository->get('ol-full', true, $this->storeDe());
        $product->setName('Voller Preis');
        $repository->save($product);
        $label = $this->label('German name', [
            ['type' => ProductCondition::class, 'attribute' => 'name', 'operator' => '{}', 'value' => 'Voller'],
        ]);

        $this->indexer->executeFull();

        self::assertSame(['ol-full'], $this->skus($this->rows($label), $this->storeDe(), -1));
        self::assertSame([], $this->skus($this->rows($label), 1, -1));
    }

    public function testParentRowsAreWrittenOnlyWithApplyToParent(): void
    {
        $withParent = $this->label('Sale parents', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')], ['apply_to_parent' => true]);
        $withoutParent = $this->label('Sale children', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);

        $this->indexer->executeFull();

        $rows = $this->rows($withParent);
        self::assertSame(['ol-bun', 'ol-conf', 'ol-conf-a', 'ol-grp', 'ol-sale20'], $this->skus($rows, 1, 0));
        $parentRow = $this->row($rows, 'ol-conf', 1, 0);
        self::assertSame($this->id('conf_a'), (int) $parentRow['parent_product_id'], 'parent row remembers the matching child');
        self::assertNull($this->row($rows, 'ol-sale20', 1, 0)['parent_product_id']);
        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($this->rows($withoutParent), 1, 0));
    }

    public function testExecuteListRefreshesChangedProductsAndTheirParents(): void
    {
        $label = $this->label('Sale parents', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')], ['apply_to_parent' => true]);
        $this->indexer->executeFull();
        self::assertContains('ol-conf', $this->skus($this->rows($label), 1, 0));

        $repository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $child = $repository->get('ol-conf-a');
        $child->setSpecialPrice(null);
        $repository->save($child);
        Bootstrap::getObjectManager()->get(IndexerRegistry::class)->get('catalog_product_price')->reindexRow($this->id('conf_a'));
        $this->indexer->executeList([$this->id('conf_a')]);

        self::assertSame(['ol-bun', 'ol-grp', 'ol-sale20'], $this->skus($this->rows($label), 1, 0));
        self::assertContains('ol-sale20', $this->skus($this->rows($label), 1, 0), 'untouched products keep their rows');
    }

    public function testLabelSaveReindexesTheLabelAndReturnsTheDiff(): void
    {
        $label = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($this->rows($label), 1, 0), 'rows exist right after save');

        $label->setConditionsSerialized($this->tree([$this->builtIn(Stock::class, 'salable_qty', '<=', '5')]));
        $this->labels->save($label);
        self::assertSame(['ol-low'], $this->skus($this->rows($label), 1, -1));

        $diff = Bootstrap::getObjectManager()->get(LabelReindexer::class)->reindexLabel((int) $label->getLabelId());
        self::assertFalse($diff->isChanged(), 'reindexing an unchanged label changes nothing');

        $cacheContext = Bootstrap::getObjectManager()->get(CacheContext::class);
        self::assertContains('cat_p_' . $this->id('low'), $cacheContext->getIdentities());
        self::assertContains('cat_p_' . $this->id('sale20'), $cacheContext->getIdentities(), 'removed products are cleaned too');
        self::assertContains('openlabel_' . $label->getLabelId(), $cacheContext->getIdentities());
    }

    public function testDeletingALabelRemovesItsRows(): void
    {
        $label = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        $id = (int) $label->getLabelId();
        self::assertNotEmpty($this->rows($label));

        $this->labels->delete($label);

        self::assertSame([], $this->rowsById($id));
    }

    public function testDisabledLabelsStayIndexed(): void
    {
        $label = $this->label('Off', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')], ['status' => LabelInterface::STATUS_DISABLED]);

        $this->indexer->executeFull();

        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($this->rows($label), 1, 0));
    }

    public function testFullReindexBuildsInTheReplicaAndSwaps(): void
    {
        $label = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        $connection = $this->resource->getConnection();
        $connection->delete($this->resource->getTableName('openlabel_index'));
        $connection->insert($this->resource->getTableName('openlabel_index_replica'), [
            'label_id' => 999999, 'product_id' => 1, 'store_id' => 1, 'customer_group_id' => -1, 'priority' => 0,
        ]);

        $this->indexer->executeFull();

        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($this->rows($label), 1, 0));
        self::assertSame([], $this->rowsById(999999), 'stale replica rows never reach the live index');
        self::assertTrue($connection->isTableExists($this->resource->getTableName('openlabel_index_replica')));
    }

    public function testTheSwapLeavesTheDeclaredSchemaUpToDate(): void
    {
        $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);

        $this->indexer->executeFull();
        $this->indexer->executeFull();

        $connection = $this->resource->getConnection();
        $comment = static fn (string $table): string => (string) $connection->fetchOne(
            'SELECT TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        $expected = 'OpenLabel index: which label applies to which product';
        self::assertSame(
            [$expected, $expected],
            [$comment($this->resource->getTableName('openlabel_index')), $comment($this->resource->getTableName('openlabel_index_replica'))],
            'renaming swaps the comments, so both tables carry the same one or setup:db:status reports a diff'
        );
    }

    public function testFullReindexCleansOnlyChangedProducts(): void
    {
        $label = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        $this->indexer->executeFull();
        $connection = $this->resource->getConnection();
        $connection->delete($this->resource->getTableName('openlabel_index'), ['product_id = ?' => $this->id('sale20')]);
        // Inside indexer actions the core CacheCleaner plugin defers and then performs the cleaning, so assert on the
        // cache itself: an entry tagged with the changed product disappears, one tagged with an unchanged product stays.
        $cache = Bootstrap::getObjectManager()->get(CacheInterface::class);
        $cache->save('kept', 'openlabel_test_changed', ['cat_p_' . $this->id('sale20')]);
        $cache->save('kept', 'openlabel_test_unchanged', ['cat_p_' . $this->id('conf_a')]);

        $this->indexer->executeFull();

        self::assertFalse($cache->load('openlabel_test_changed'), 'pages of the changed product are cleaned');
        self::assertSame('kept', $cache->load('openlabel_test_unchanged'), 'unchanged products are not cleaned');
        self::assertSame(['ol-conf-a', 'ol-sale20'], $this->skus($this->rows($label), 1, 0));
        $cache->remove('openlabel_test_unchanged');
    }

    public function testPriceIndexerRunsRefreshOurRows(): void
    {
        $label = $this->label('Sale', [$this->builtIn(OnSale::class, 'on_sale', '==', '1')]);
        self::assertNotContains('ol-full', $this->skus($this->rows($label), 1, 0));
        $repository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $product = $repository->get('ol-full');
        $product->setSpecialPrice(50);
        $repository->save($product);

        Bootstrap::getObjectManager()->get(IndexerRegistry::class)->get('catalog_product_price')->reindexRow($this->id('full'));

        self::assertContains('ol-full', $this->skus($this->rows($label), 1, 0), 'the price indexer plugin schedules our rows');
    }

    public function testDailyCronReindexesDateRelativeLabels(): void
    {
        $label = $this->label('New', [$this->builtIn(IsNew::class, 'days_since_created', '<=', '30')]);
        $connection = $this->resource->getConnection();
        $connection->delete($this->resource->getTableName('openlabel_index'), ['label_id = ?' => $label->getLabelId()]);

        Bootstrap::getObjectManager()->get(\Iranimij\OpenLabel\Cron\DailyIsNew::class)->execute();

        self::assertContains('ol-full', $this->skus($this->rows($label), 1, -1));
    }

    public function testHourlyCronCleansProductsOfLabelsWhoseWindowJustOpened(): void
    {
        $label = $this->label('Window', [$this->builtIn(Stock::class, 'salable_qty', '<=', '5')], [
            'valid_from' => gmdate('Y-m-d H:i:s', time() - 1800),
        ]);
        $cacheContext = Bootstrap::getObjectManager()->get(CacheContext::class);
        $cacheContext->flush();

        Bootstrap::getObjectManager()->get(\Iranimij\OpenLabel\Cron\HourlyTransitions::class)->execute();

        self::assertContains('cat_p_' . $this->id('low'), $cacheContext->getIdentities());
        self::assertContains('openlabel_' . $label->getLabelId(), $cacheContext->getIdentities());
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @param array<string, mixed> $data
     */
    private function label(string $name, array $conditions, array $data = []): LabelInterface
    {
        $fixture = Bootstrap::getObjectManager()->get(LabelFixture::class);
        /** @var LabelInterface $label */
        $label = $fixture->apply(array_merge([
            'name' => $name,
            'design_id' => (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId(),
            'conditions_serialized' => $this->tree($conditions),
        ], $data));
        $this->created[] = $label;

        return $label;
    }

    /** @var LabelInterface[] */
    private ?array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created ?? [] as $label) {
            try {
                $this->labels->deleteById((int) $label->getLabelId());
            } catch (\Exception) {
                // already gone
            }
        }
        $this->created = [];
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     */
    private function tree(array $conditions): string
    {
        return Bootstrap::getObjectManager()->get(Json::class)->serialize([
            'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => $conditions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function builtIn(string $class, string $attribute, string $operator, string $value): array
    {
        return ['type' => $class, 'attribute' => $attribute, 'operator' => $operator, 'value' => $value];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LabelInterface $label): array
    {
        return $this->rowsById((int) $label->getLabelId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsById(int $labelId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['i' => $this->resource->getTableName('openlabel_index')])
            ->joinLeft(['e' => $this->resource->getTableName('catalog_product_entity')], 'e.entity_id = i.product_id', ['sku'])
            ->where('i.label_id = ?', $labelId)
            ->order(['e.sku', 'i.store_id', 'i.customer_group_id']);

        // The integration database also holds the sample-data catalog; only the fixture products matter here.
        return array_values(array_filter(
            $connection->fetchAll($select),
            static fn (array $row): bool => str_starts_with((string) $row['sku'], 'ol-')
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return string[]
     */
    private function skus(array $rows, int $storeId, int $groupId): array
    {
        $skus = [];
        foreach ($rows as $row) {
            if ((int) $row['store_id'] === $storeId && (int) $row['customer_group_id'] === $groupId) {
                $skus[] = $row['sku'];
            }
        }
        sort($skus);

        return array_values(array_unique($skus));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return int[]
     */
    private function groupsOf(array $rows, string $sku, int $storeId): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if ($row['sku'] === $sku && (int) $row['store_id'] === $storeId) {
                $groups[] = (int) $row['customer_group_id'];
            }
        }
        sort($groups);

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function row(array $rows, string $sku, int $storeId, int $groupId): array
    {
        foreach ($rows as $row) {
            if ($row['sku'] === $sku && (int) $row['store_id'] === $storeId && (int) $row['customer_group_id'] === $groupId) {
                return $row;
            }
        }
        self::fail("No index row for $sku / store $storeId / group $groupId");
    }

    private function id(string $fixture): int
    {
        return (int) DataFixtureStorageManager::getStorage()->get($fixture)->getId();
    }

    private function storeDe(): int
    {
        return (int) DataFixtureStorageManager::getStorage()->get('store_de')->getId();
    }
}
