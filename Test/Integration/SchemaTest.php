<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The five OpenLabel tables and the index primary key are a public contract from 1.0 (02 · Architecture §2).
 */
class SchemaTest extends TestCase
{
    private ?ResourceConnection $resource = null;

    protected function setUp(): void
    {
        $this->resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
    }

    public function testEveryTableExistsWithItsColumns(): void
    {
        $connection = $this->resource->getConnection();
        foreach (self::tables() as [$table, $columns]) {
            $name = $this->resource->getTableName($table);

            self::assertTrue($connection->isTableExists($name), "$table must exist");
            $actual = array_keys($connection->describeTable($name));
            foreach ($columns as $column) {
                self::assertContains($column, $actual, "$table.$column must exist");
            }
        }
    }

    /**
     * @return array<string, array{string, string[]}>
     */
    public static function tables(): array
    {
        return [
            'label' => ['openlabel_label', [
                'label_id', 'name', 'status', 'priority', 'store_ids', 'customer_group_ids', 'valid_from', 'valid_to',
                'conditions_serialized', 'design_id', 'apply_to_parent', 'hide_lower_priority', 'created_at', 'updated_at',
            ]],
            'design' => ['openlabel_design', [
                'design_id', 'name', 'type', 'shape', 'image_path', 'image_width', 'image_height', 'bg_color', 'text_color',
                'border_color', 'border_width', 'font_size', 'size_mode', 'width', 'height', 'opacity', 'rotation',
                'custom_css', 'is_system', 'created_at', 'updated_at',
            ]],
            'design_store' => ['openlabel_design_store', ['design_id', 'store_id', 'text', 'alt_text', 'tooltip']],
            'placement' => ['openlabel_placement', [
                'placement_id', 'label_id', 'area', 'position', 'pin_physical_side', 'design_id', 'offset_x', 'offset_y',
                'max_labels', 'stacking', 'gap', 'sort_order',
            ]],
            'index' => ['openlabel_index', [
                'label_id', 'product_id', 'store_id', 'customer_group_id', 'parent_product_id', 'priority', 'valid_from', 'valid_to',
            ]],
            'index_replica' => ['openlabel_index_replica', ['label_id', 'product_id', 'store_id', 'customer_group_id']],
            'label_product' => ['openlabel_label_product', ['label_id', 'product_id']],
        ];
    }

    public function testIndexPrimaryKeyIsLabelProductStoreGroupAndGroupIsSignedNotNull(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('openlabel_index');

        $primary = array_values(array_filter(
            $connection->describeTable($table),
            static fn (array $column): bool => (bool) $column['PRIMARY']
        ));
        self::assertSame(
            ['label_id', 'product_id', 'store_id', 'customer_group_id'],
            array_column($primary, 'COLUMN_NAME')
        );
        $group = $connection->describeTable($table)['customer_group_id'];
        self::assertFalse($group['NULLABLE'], 'customer_group_id is NOT NULL; -1 means all groups');
        self::assertEmpty($group['UNSIGNED'], 'customer_group_id is signed so the -1 sentinel fits');
        self::assertNull($group['DEFAULT'], 'no DB default: the indexer always writes the group explicitly (XSD forbids -1)');
    }

    public function testPlacementsAreDeletedWithTheirLabel(): void
    {
        $connection = $this->resource->getConnection();
        $keys = $connection->getForeignKeys($this->resource->getTableName('openlabel_placement'));

        $toLabel = array_values(array_filter(
            $keys,
            static fn (array $key): bool => $key['COLUMN_NAME'] === 'label_id'
        ));
        self::assertCount(1, $toLabel);
        self::assertSame('CASCADE', $toLabel[0]['ON_DELETE']);
    }

    public function testManualAssignmentsGoWithTheirLabelAndProduct(): void
    {
        $keys = $this->resource->getConnection()->getForeignKeys($this->resource->getTableName('openlabel_label_product'));
        $byColumn = [];
        foreach ($keys as $key) {
            $byColumn[$key['COLUMN_NAME']] = [$key['REF_TABLE_NAME'], $key['ON_DELETE']];
        }
        ksort($byColumn);

        self::assertSame([
            'label_id' => [$this->resource->getTableName('openlabel_label'), 'CASCADE'],
            'product_id' => [$this->resource->getTableName('catalog_product_entity'), 'CASCADE'],
        ], $byColumn);
    }
}
