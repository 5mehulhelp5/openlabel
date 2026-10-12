<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\Context;

/**
 * Base of conditions on what shoppers did with a product recently (units sold, views): a count over the last N
 * days and the product's rank by that count, both from one bounded aggregation per window, joined as a subquery.
 * Counted per website (all its store views). A product with no activity counts 0 and has no rank, so a rank
 * condition ("in the top 10") never matches it.
 *
 * The result changes every day, so labels using these conditions are reindexed by the daily cron.
 */
abstract class RecentActivity extends AbstractBuiltIn
{
    /**
     * @param Context $context
     * @param ResourceConnection $resource
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        protected readonly ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Attribute code => [days, 'count'|'rank'].
     *
     * @return array<string, array{int, string}>
     */
    abstract protected function windows(): array;

    /**
     * Prefix of the join aliases, e.g. "ol_sold" → ol_sold_30.
     *
     * @return string
     */
    abstract protected function aliasPrefix(): string;

    /**
     * One row per product with activity in the window: columns product_id and amount, website's stores only.
     *
     * @param int $days
     * @param Select $storeIds SELECT store_id of the rule's website
     * @return Select|null null when the data source is missing (e.g. the reports module is not installed)
     */
    abstract protected function activitySelect(int $days, Select $storeIds): ?Select;

    /**
     * @inheritDoc
     */
    public function isDateRelative(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTablesToJoin()
    {
        [$days] = $this->window();
        $connection = $this->resource->getConnection();
        $storeIds = $connection->select()
            ->from($this->resource->getTableName('store'), ['store_id'])
            ->where('website_id = ?', $this->websiteId());
        $activity = $this->activitySelect($days, $storeIds);
        if ($activity === null) {
            // Nothing to count: an empty derived table with the same columns keeps the SQL valid.
            $subquery = (string) $connection->select()
                ->from(['none' => $this->resource->getTableName('store')], [
                    'product_id' => new \Zend_Db_Expr('NULL'),
                    'amount' => new \Zend_Db_Expr('0'),
                    'activity_rank' => new \Zend_Db_Expr('NULL'),
                ])
                ->where('1 = 0');
        } else {
            $ranked = $connection->select()
                ->from(['a' => $activity], [
                    'product_id',
                    'amount',
                    'activity_rank' => new \Zend_Db_Expr('RANK() OVER (ORDER BY a.amount DESC)'),
                ])
                ->where('a.amount > 0');
            $subquery = (string) $ranked;
        }

        return [
            $this->alias() => [
                'name' => new \Zend_Db_Expr('(' . $subquery . ')'),
                'condition' => $this->alias() . '.product_id = e.entity_id',
                'columns' => [],
            ],
        ];
    }

    /**
     * The core Sql Builder accepts expressions only as Zend_Db_Expr instances, hence the widened return type.
     *
     * @return \Zend_Db_Expr
     * @phpstan-ignore method.childReturnType, method.childReturnType (AbstractCondition and ConditionInterface both declare string)
     */
    public function getMappedSqlField()
    {
        [, $kind] = $this->window();

        return new \Zend_Db_Expr($kind === 'rank'
            ? $this->alias() . '.activity_rank'
            : 'COALESCE(' . $this->alias() . '.amount, 0)');
    }

    /**
     * @inheritDoc
     */
    public function getSqlGuard(): ?string
    {
        [, $kind] = $this->window();

        return $kind === 'rank' ? $this->alias() . '.activity_rank IS NOT NULL' : null;
    }

    /**
     * @inheritDoc
     */
    protected function productValue(Product $product): mixed
    {
        [, $kind] = $this->window();
        $value = $product->getData((string) $this->getAttribute());
        if ($value === null || $value === '') {
            return $kind === 'rank' ? null : 0;
        }

        return (int) $value;
    }

    /**
     * @return array{int, string}
     */
    private function window(): array
    {
        $windows = $this->windows();

        return $windows[(string) $this->getAttribute()] ?? reset($windows);
    }

    /**
     * @return string
     */
    private function alias(): string
    {
        [$days] = $this->window();

        return $this->aliasPrefix() . '_' . $days;
    }
}
