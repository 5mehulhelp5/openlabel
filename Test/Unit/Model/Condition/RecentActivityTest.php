<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\Bestseller;

class RecentActivityTest extends ConditionTestCase
{
    public function testUnitsSoldCountProductsWithoutSalesAsZero(): void
    {
        $condition = $this->condition(Bestseller::class, 'sold_last_30d', '<=', '0');

        self::assertTrue($condition->validate($this->product([])));
        self::assertFalse($condition->validate($this->product(['sold_last_30d' => 3])));
        self::assertSame('COALESCE(ol_sold_30.amount, 0)', (string) $condition->getMappedSqlField());
        self::assertNull($condition->getSqlGuard());
    }

    public function testRankNeverMatchesProductsWithoutSales(): void
    {
        $condition = $this->condition(Bestseller::class, 'sales_rank_30d', '<=', '10');

        self::assertFalse($condition->validate($this->product([])), 'no sales, no rank');
        self::assertTrue($condition->validate($this->product(['sales_rank_30d' => 4])));
        self::assertSame('ol_sold_30.activity_rank', (string) $condition->getMappedSqlField());
        self::assertSame('ol_sold_30.activity_rank IS NOT NULL', $condition->getSqlGuard());
    }

    public function testRecentActivityIsReindexedDailyAndIsNotPerCustomerGroup(): void
    {
        $condition = $this->condition(Bestseller::class, 'sold_last_30d', '>=', '1');

        self::assertTrue($condition->isDateRelative());
        self::assertFalse($condition->requiresCustomerGroup());
    }
}
