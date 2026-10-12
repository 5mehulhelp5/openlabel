<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Framework\DB\Select;

/**
 * Most viewed: product page views in the last 7 or 30 days, and the view rank over the last 30 days, from
 * Magento's own report events ("Stores › Configuration › General › Reports" must keep product views enabled).
 * Without the reports tables every product counts 0 views and has no rank.
 */
class MostViewed extends RecentActivity
{
    public const VIEWS_7D = 'views_last_7d';
    public const VIEWS_30D = 'views_last_30d';
    public const RANK_30D = 'view_rank_30d';

    /** report_event_types: catalog_product_view. */
    private const EVENT_PRODUCT_VIEW = 1;

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [
            self::VIEWS_7D => __('Product views in the last 7 days'),
            self::VIEWS_30D => __('Product views in the last 30 days'),
            self::RANK_30D => __('Most viewed rank (last 30 days)'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function windows(): array
    {
        return [
            self::VIEWS_7D => [7, 'count'],
            self::VIEWS_30D => [30, 'count'],
            self::RANK_30D => [30, 'rank'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function aliasPrefix(): string
    {
        return 'ol_viewed';
    }

    /**
     * @inheritDoc
     */
    protected function activitySelect(int $days, Select $storeIds): ?Select
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('report_event');
        if (!$connection->isTableExists($table)) {
            return null;
        }

        return $connection->select()
            ->from(['v' => $table], ['product_id' => 'object_id', 'amount' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('v.event_type_id = ?', self::EVENT_PRODUCT_VIEW)
            ->where('v.store_id IN (' . $storeIds . ')')
            ->where(sprintf('v.logged_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', $days))
            ->group('v.object_id');
    }
}
