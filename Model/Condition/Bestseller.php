<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Framework\DB\Select;

/**
 * Bestseller: units sold in the last 7, 30 or 90 days, and the sales rank over the last 30 days ("rank at most 10"
 * = top 10). Counted from order items (not the reports tables, whose refresh many shops never run); canceled and
 * refunded quantities do not count. A configurable sale counts for the configurable product.
 */
class Bestseller extends RecentActivity
{
    public const SOLD_7D = 'sold_last_7d';
    public const SOLD_30D = 'sold_last_30d';
    public const SOLD_90D = 'sold_last_90d';
    public const RANK_30D = 'sales_rank_30d';

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [
            self::SOLD_7D => __('Units sold in the last 7 days'),
            self::SOLD_30D => __('Units sold in the last 30 days'),
            self::SOLD_90D => __('Units sold in the last 90 days'),
            self::RANK_30D => __('Bestseller rank (last 30 days)'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function windows(): array
    {
        return [
            self::SOLD_7D => [7, 'count'],
            self::SOLD_30D => [30, 'count'],
            self::SOLD_90D => [90, 'count'],
            self::RANK_30D => [30, 'rank'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function aliasPrefix(): string
    {
        return 'ol_sold';
    }

    /**
     * @inheritDoc
     */
    protected function activitySelect(int $days, Select $storeIds): ?Select
    {
        $connection = $this->resource->getConnection();

        return $connection->select()
            ->from(['i' => $this->resource->getTableName('sales_order_item')], [
                'product_id',
                'amount' => new \Zend_Db_Expr(
                    'SUM(i.qty_ordered - COALESCE(i.qty_canceled, 0) - COALESCE(i.qty_refunded, 0))'
                ),
            ])
            ->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = i.order_id', [])
            ->where('i.parent_item_id IS NULL')
            ->where('i.product_id IS NOT NULL')
            ->where('o.state <> ?', 'canceled')
            ->where('i.store_id IN (' . $storeIds . ')')
            ->where(sprintf('i.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $days))
            ->group('i.product_id');
    }
}
