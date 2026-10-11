<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Catalog\Model\Product;

/**
 * Average rating in stars (review summary is stored in percent; 100 % = 5 stars). Products without reviews never match.
 */
class Rating extends AbstractBuiltIn
{
    use ReviewSummaryJoin;

    public const RATING = 'rating';

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [self::RATING => __('Average rating (stars)')];
    }

    /**
     * The core Sql Builder accepts expressions only as Zend_Db_Expr instances, hence the widened return type.
     *
     * @return \Zend_Db_Expr
     * @phpstan-ignore method.childReturnType, method.childReturnType (AbstractCondition and ConditionInterface both declare string)
     */
    public function getMappedSqlField()
    {
        return new \Zend_Db_Expr('(' . self::REVIEW_ALIAS . '.rating_summary / 20)');
    }

    /**
     * A product without a rating has no "stars": it never matches a rating condition ("rating < 3" means rated
     * poorly, not "not rated").
     *
     * @return string
     */
    public function getSqlGuard(): ?string
    {
        return self::REVIEW_ALIAS . '.rating_summary IS NOT NULL';
    }

    /**
     * @inheritDoc
     */
    protected function productValue(Product $product): mixed
    {
        $summary = $product->getData('rating_summary');

        return $summary === null || $summary === '' ? null : (float) $summary / 20;
    }
}
