<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Catalog\Model\Product;

/**
 * Number of approved reviews in the rule's store view.
 */
class ReviewCount extends AbstractBuiltIn
{
    use ReviewSummaryJoin;

    public const REVIEW_COUNT = 'review_count';

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [self::REVIEW_COUNT => __('Number of reviews')];
    }

    /**
     * The core Sql Builder accepts expressions only as Zend_Db_Expr instances, hence the widened return type.
     *
     * @return \Zend_Db_Expr
     * @phpstan-ignore method.childReturnType, method.childReturnType (AbstractCondition and ConditionInterface both declare string)
     */
    public function getMappedSqlField()
    {
        // No summary row means no reviews: "review count = 0" finds products nobody reviewed yet.
        return new \Zend_Db_Expr('COALESCE(' . self::REVIEW_ALIAS . '.reviews_count, 0)');
    }

    /**
     * @inheritDoc
     */
    protected function productValue(Product $product): mixed
    {
        $count = $product->getData('reviews_count');

        return $count === null || $count === '' ? null : (int) $count;
    }
}
