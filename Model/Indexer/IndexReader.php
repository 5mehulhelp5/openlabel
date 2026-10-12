<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Indexer;

use Magento\Framework\App\ResourceConnection;

/**
 * Read-only questions to the index: how many products a label matches in a store view, and which.
 */
class IndexReader
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int $labelId
     * @param int $storeId
     * @return int distinct products with a row for the label in the store view (any customer group)
     */
    public function countProducts(int $labelId, int $storeId): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('openlabel_index'), [new \Zend_Db_Expr('COUNT(DISTINCT product_id)')])
            ->where('label_id = ?', $labelId)
            ->where('store_id = ?', $storeId);

        return (int) $connection->fetchOne($select);
    }

    /**
     * @param int $labelId
     * @param int $storeId
     * @param int $limit
     * @return string[] SKUs of matched products, lowest product id first
     */
    public function skus(int $labelId, int $storeId, int $limit): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(['i' => $this->resource->getTableName('openlabel_index')], [])
            ->join(['e' => $this->resource->getTableName('catalog_product_entity')], 'e.entity_id = i.product_id', ['sku'])
            ->where('i.label_id = ?', $labelId)
            ->where('i.store_id = ?', $storeId)
            ->order('e.entity_id ASC')
            ->limit($limit);

        return array_map('strval', $connection->fetchCol($select));
    }

    /**
     * @return int all rows in the index
     */
    public function countRows(): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('openlabel_index'), [new \Zend_Db_Expr('COUNT(*)')])
        );
    }

    /**
     * Labels a product shows in any store view and customer group (product edit page).
     *
     * @param int $productId
     * @return array<int, string> label id => name
     */
    public function labelsOfProduct(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(['i' => $this->resource->getTableName('openlabel_index')], [])
            ->join(['l' => $this->resource->getTableName('openlabel_label')], 'l.label_id = i.label_id', ['label_id', 'name'])
            ->where('i.product_id = ?', $productId)
            ->order(['l.priority', 'l.label_id']);
        $labels = [];
        foreach ($connection->fetchAll($select) as $row) {
            $labels[(int) $row['label_id']] = (string) $row['name'];
        }

        return $labels;
    }
}
