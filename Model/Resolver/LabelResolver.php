<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Resolver;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\DesignFactory;
use Iranimij\OpenLabel\Model\Indexer\GroupSelector;
use Iranimij\OpenLabel\Model\PlacementFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * One SELECT over the index joined with labels, placements, designs and store texts, then the Arranger rules.
 */
class LabelResolver implements LabelResolverInterface
{
    private const DESIGN_COLUMNS = [
        'design_id', 'name', 'type', 'shape', 'image_path', 'image_width', 'image_height', 'bg_color', 'text_color',
        'border_color', 'border_width', 'font_size', 'size_mode', 'width', 'height', 'opacity', 'rotation', 'custom_css', 'is_system',
        'animation', 'hover_effect',
    ];
    private const PLACEMENT_COLUMNS = [
        'placement_id', 'area', 'position', 'pin_physical_side', 'design_id', 'offset_x', 'offset_y', 'max_labels',
        'stacking', 'gap', 'sort_order',
    ];

    /**
     * @param ResourceConnection $resource
     * @param Arranger $arranger
     * @param PlacementFactory $placementFactory
     * @param DesignFactory $designFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Arranger $arranger,
        private readonly PlacementFactory $placementFactory,
        private readonly DesignFactory $designFactory,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getForProducts(array $productIds, int $storeId, int $customerGroupId): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }
        $result = [];
        foreach ($this->arranger->arrange($this->fetchRows($productIds, $storeId, $customerGroupId)) as $productId => $rows) {
            foreach ($rows as $row) {
                $result[$productId][] = $this->toResolvedLabel($row);
            }
        }

        return $result;
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @param int $customerGroupId
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(array $productIds, int $storeId, int $customerGroupId): array
    {
        $connection = $this->resource->getConnection();
        $now = $this->dateTime->gmtDate();
        $designColumns = [];
        foreach (self::DESIGN_COLUMNS as $column) {
            $designColumns['design_' . $column] = $column;
        }
        $placementColumns = [];
        foreach (self::PLACEMENT_COLUMNS as $column) {
            $placementColumns['placement_' . $column] = $column;
        }
        $select = $connection->select()
            ->from(['i' => $this->resource->getTableName('openlabel_index')], ['product_id', 'parent_product_id', 'label_id'])
            ->join(
                ['l' => $this->resource->getTableName('openlabel_label')],
                'l.label_id = i.label_id AND l.status = ' . LabelInterface::STATUS_ENABLED,
                ['name', 'priority', 'hide_lower_priority', 'hide_on_zero_variable']
            )
            ->join(['p' => $this->resource->getTableName('openlabel_placement')], 'p.label_id = l.label_id', $placementColumns)
            ->join(['d' => $this->resource->getTableName('openlabel_design')], 'd.design_id = COALESCE(p.design_id, l.design_id)', $designColumns)
            ->joinLeft(
                ['ds' => $this->resource->getTableName('openlabel_design_store')],
                'ds.design_id = d.design_id AND ds.store_id = ' . $storeId,
                []
            )
            ->joinLeft(
                ['ds0' => $this->resource->getTableName('openlabel_design_store')],
                'ds0.design_id = d.design_id AND ds0.store_id = ' . DesignInterface::DEFAULT_STORE_ID,
                [
                    'text' => new \Zend_Db_Expr('COALESCE(ds.text, ds0.text)'),
                    'alt_text' => new \Zend_Db_Expr('COALESCE(ds.alt_text, ds0.alt_text)'),
                    'tooltip' => new \Zend_Db_Expr('COALESCE(ds.tooltip, ds0.tooltip)'),
                ]
            )
            ->where('i.store_id = ?', $storeId)
            ->where('i.product_id IN (?)', $productIds, \Zend_Db::INT_TYPE)
            ->where('i.customer_group_id IN (?)', [GroupSelector::ALL_GROUPS, $customerGroupId], \Zend_Db::INT_TYPE)
            ->where('i.valid_from IS NULL OR i.valid_from <= ?', $now)
            ->where('i.valid_to IS NULL OR i.valid_to >= ?', $now)
            ->order(['i.product_id', 'l.priority', 'l.label_id', 'p.sort_order']);
        $rows = $connection->fetchAll($select);
        foreach ($rows as &$row) {
            $row['area'] = $row['placement_area'];
            $row['position'] = $row['placement_position'];
            $row['max_labels'] = $row['placement_max_labels'];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return ResolvedLabel
     */
    private function toResolvedLabel(array $row): ResolvedLabel
    {
        $placementData = ['label_id' => (int) $row['label_id']];
        foreach (self::PLACEMENT_COLUMNS as $column) {
            $placementData[$column] = $row['placement_' . $column];
        }
        $placement = $this->placementFactory->create();
        $placement->setData($placementData);
        $designData = [];
        foreach (self::DESIGN_COLUMNS as $column) {
            $designData[$column] = $row['design_' . $column];
        }
        $design = $this->designFactory->create();
        $design->setData($designData);
        $design->setStoreTexts([
            DesignInterface::DEFAULT_STORE_ID => ['text' => $row['text'], 'alt_text' => $row['alt_text'], 'tooltip' => $row['tooltip']],
        ]);

        return new ResolvedLabel(
            (int) $row['label_id'],
            (string) $row['name'],
            (int) $row['priority'],
            (bool) $row['hide_lower_priority'],
            (int) $row['product_id'],
            $row['parent_product_id'] === null ? null : (int) $row['parent_product_id'],
            $placement,
            $design,
            (bool) $row['hide_on_zero_variable']
        );
    }
}
