<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\ImportExport\Smile;

use Iranimij\OpenLabel\Console\ImportSmile;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\ImportExport\Smile\Converter;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Filesystem;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * openlabel:import-smile against Smile product label tables created by this test (the Smile module is not
 * installed): labels, images, conditions and index rows; a second run skips what was imported.
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(AttributeFixture::class, as: 'attr')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-smile-eco', '$attr.attribute_code$' => '$attr.option_1$'], 'eco')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-smile-plain', '$attr.attribute_code$' => '$attr.option_2$'], 'plain')]
class ImportSmileTest extends TestCase
{
    /** A 2 × 1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAC0lEQVR42mNgAAIAAAUAAen63NgAAAAASUVORK5CYII=';

    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $labelIds = $connection->fetchCol(
            $connection->select()->from($resource->getTableName('openlabel_label'), ['label_id'])
                ->where('name IN (?)', ['ol-smile Eco', 'ol-smile Off'])
        );
        if ($labelIds !== []) {
            $connection->delete($resource->getTableName('openlabel_index'), ['label_id IN (?)' => $labelIds]);
            $connection->delete($resource->getTableName('openlabel_label'), ['label_id IN (?)' => $labelIds]);
        }
        $connection->delete($resource->getTableName('openlabel_design'), ['name LIKE ?' => 'Smile: ol-smile %']);
        $connection->dropTable($resource->getTableName('smile_productlabel_store'));
        $connection->dropTable($resource->getTableName('smile_productlabel'));
        $media = Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA);
        $media->delete(Converter::MEDIA_DIR . '/ol-smile-eco.png');
        $media->delete(ImageUrl::MEDIA_DIR . '/designs/ol-smile-eco.png');
    }

    public function testWithoutSmileTablesTheCommandSaysSo(): void
    {
        $tester = $this->tester();

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('No Smile product label tables', $tester->getDisplay());
    }

    public function testSmileLabelsBecomeOpenLabelLabelsAndASecondRunSkipsThem(): void
    {
        $attribute = DataFixtureStorageManager::getStorage()->get('attr');
        $storeId = (int) Bootstrap::getObjectManager()->get(\Magento\Store\Model\StoreManagerInterface::class)->getDefaultStoreView()->getId();
        $this->smileTables([
            [1, 'ol-smile Eco', 1, (int) $attribute->getAttributeId(), (string) $attribute->getData('option_1'), 'ol-smile-eco.png', 'top-right', 'lower-left', '1,2', 'Eco friendly', [$storeId]],
            [2, 'ol-smile Off', 0, (int) $attribute->getAttributeId(), (string) $attribute->getData('option_2'), 'ol-smile-missing.png', 'top-left', 'top-left', '2', '', [0]],
        ]);

        $tester = $this->tester();
        self::assertSame(0, $tester->execute([]), $tester->getDisplay());

        self::assertStringContainsString('Imported 1 Smile label(s).', $tester->getDisplay());
        self::assertStringContainsString('Smile label #2 "ol-smile Off": image "ol-smile-missing.png" not found', $tester->getDisplay());
        $connection = $this->connection();
        $label = $connection->fetchRow("SELECT * FROM openlabel_label WHERE name = 'ol-smile Eco'");
        self::assertSame((string) $storeId, (string) $label['store_ids']);
        self::assertSame(
            [['listing', 'tr'], ['product', 'bl']],
            array_map(
                static fn (array $row): array => [$row['area'], $row['position']],
                $connection->fetchAll('SELECT area, position FROM openlabel_placement WHERE label_id = ? ORDER BY sort_order', [$label['label_id']])
            )
        );
        $design = $connection->fetchRow('SELECT * FROM openlabel_design WHERE design_id = ?', [$label['design_id']]);
        self::assertSame(['image', 'designs/ol-smile-eco.png', '2', '1'], [$design['type'], $design['image_path'], (string) $design['image_width'], (string) $design['image_height']]);
        $products = $connection->fetchCol(
            'SELECT e.sku FROM openlabel_index i JOIN catalog_product_entity e ON e.entity_id = i.product_id'
            . ' WHERE i.label_id = ? GROUP BY e.sku',
            [$label['label_id']]
        );
        self::assertSame(['ol-smile-eco'], $products, 'only the product with the Smile label\'s option');

        $again = $this->tester();
        self::assertSame(0, $again->execute([]));
        self::assertStringContainsString('Imported 0 Smile label(s); 1 already imported earlier, skipped.', $again->getDisplay());
        self::assertSame('1', (string) $connection->fetchOne("SELECT COUNT(*) FROM openlabel_label WHERE name = 'ol-smile Eco'"));
    }

    public function testDryRunWritesNothing(): void
    {
        $attribute = DataFixtureStorageManager::getStorage()->get('attr');
        $this->smileTables([
            [1, 'ol-smile Eco', 1, (int) $attribute->getAttributeId(), (string) $attribute->getData('option_1'), 'ol-smile-eco.png', 'top-right', 'lower-left', '2', '', [0]],
        ]);

        $tester = $this->tester();
        self::assertSame(0, $tester->execute(['--dry-run' => true]));

        self::assertStringContainsString('Dry run, nothing written. Would import 1 Smile label(s).', $tester->getDisplay());
        self::assertSame('0', (string) $this->connection()->fetchOne("SELECT COUNT(*) FROM openlabel_label WHERE name = 'ol-smile Eco'"));
    }

    /**
     * The two Smile tables with the columns the importer reads, plus the image file of the first label.
     *
     * @param array<int, array{int, string, int, int, string, string, string, string, string, string, int[]}> $labels
     */
    private function smileTables(array $labels): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $connection->newTable($resource->getTableName('smile_productlabel'))
            ->addColumn('product_label_id', Table::TYPE_INTEGER, null, ['primary' => true, 'nullable' => false])
            ->addColumn('is_active', Table::TYPE_BOOLEAN, null, ['nullable' => false, 'default' => 1])
            ->addColumn('name', Table::TYPE_TEXT, 255, ['nullable' => false])
            ->addColumn('attribute_id', Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false])
            ->addColumn('option_id', Table::TYPE_TEXT, 255, ['nullable' => false])
            ->addColumn('image', Table::TYPE_BLOB, null, [])
            ->addColumn('position_category_list', Table::TYPE_TEXT, 255, [])
            ->addColumn('position_product_view', Table::TYPE_TEXT, 255, [])
            ->addColumn('display_on', Table::TYPE_TEXT, 255, [])
            ->addColumn('alt', Table::TYPE_TEXT, 255, []);
        $connection->createTable($table);
        $store = $connection->newTable($resource->getTableName('smile_productlabel_store'))
            ->addColumn('product_label_id', Table::TYPE_INTEGER, null, ['primary' => true, 'nullable' => false])
            ->addColumn('store_id', Table::TYPE_SMALLINT, null, ['primary' => true, 'unsigned' => true, 'nullable' => false]);
        $connection->createTable($store);
        foreach ($labels as [$id, $name, $active, $attributeId, $optionId, $image, $listing, $product, $display, $alt, $stores]) {
            $connection->insert($resource->getTableName('smile_productlabel'), [
                'product_label_id' => $id, 'is_active' => $active, 'name' => $name, 'attribute_id' => $attributeId,
                'option_id' => $optionId, 'image' => $image, 'position_category_list' => $listing,
                'position_product_view' => $product, 'display_on' => $display, 'alt' => $alt,
            ]);
            foreach ($stores as $storeId) {
                $connection->insert($resource->getTableName('smile_productlabel_store'), ['product_label_id' => $id, 'store_id' => $storeId]);
            }
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA)
            ->writeFile(Converter::MEDIA_DIR . '/ol-smile-eco.png', base64_decode(self::PNG));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(Bootstrap::getObjectManager()->create(ImportSmile::class));
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }
}
