<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Magento\Framework\App\ResourceConnection;

/**
 * Products assigned to labels by hand (1.1, `openlabel_label_product`), from the product edit page. The indexer
 * adds them to the label's matches; status, store views, customer groups and dates of the label still apply.
 */
class ManualAssignments
{
    private const TABLE = 'openlabel_label_product';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int $productId
     * @return int[] label ids
     */
    public function forProduct(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['label_id'])
            ->where('product_id = ?', $productId)
            ->order('label_id');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @param int $labelId
     * @return int[] product ids
     */
    public function forLabel(int $labelId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['product_id'])
            ->where('label_id = ?', $labelId)
            ->order('product_id');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @param int $productId
     * @param int[] $labelIds the complete new set for the product
     * @return int[] labels added or removed (to reindex)
     */
    public function setForProduct(int $productId, array $labelIds): array
    {
        $labelIds = array_values(array_unique(array_filter(array_map('intval', $labelIds), static fn (int $id): bool => $id > 0)));
        $current = $this->forProduct($productId);
        $added = array_values(array_diff($labelIds, $current));
        $removed = array_values(array_diff($current, $labelIds));
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        if ($removed !== []) {
            $connection->delete($table, ['product_id = ?' => $productId, 'label_id IN (?)' => $removed]);
        }
        if ($added !== []) {
            $connection->insertMultiple($table, array_map(
                static fn (int $labelId): array => ['label_id' => $labelId, 'product_id' => $productId],
                $added
            ));
        }
        $changed = array_merge($added, $removed);
        sort($changed);

        return $changed;
    }
}
