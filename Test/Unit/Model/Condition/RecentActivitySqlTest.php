<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\Bestseller;
use Iranimij\OpenLabel\Model\Condition\MostViewed;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * The SQL the activity conditions contribute: one aggregated, ranked subquery per window, joined by alias.
 * Selects are recorded instead of rendered: S1, S2, ... in the order they are created.
 */
class RecentActivitySqlTest extends ConditionTestCase
{
    /** @var array<int, array<int, array{string, array<int, mixed>}>> select number => calls */
    private array $calls = [];

    private bool $reportsInstalled = true;

    public function testBestsellerJoinsOneRankedSubqueryOfTheWindowsUnitsSold(): void
    {
        $join = $this->condition(Bestseller::class, 'sold_last_30d', '>=', '5', ['resource' => $this->resource()])
            ->getTablesToJoin();

        self::assertSame(['ol_sold_30'], array_keys($join));
        self::assertSame('(S3)', (string) $join['ol_sold_30']['name']);
        self::assertSame('ol_sold_30.product_id = e.entity_id', $join['ol_sold_30']['condition']);
        self::assertSame([], $join['ol_sold_30']['columns']);

        self::assertSame(['from', ['store', ['store_id']]], $this->calls[1][0]);
        self::assertContains(['where', ['website_id = ?', 1]], $this->calls[1], 'the rule\'s website only');

        $activity = $this->calls[2];
        self::assertSame('from', $activity[0][0]);
        self::assertSame(['i' => 'sales_order_item'], $activity[0][1][0]);
        self::assertStringContainsString('qty_canceled', (string) $activity[0][1][1]['amount']);
        self::assertStringContainsString('qty_refunded', (string) $activity[0][1][1]['amount']);
        self::assertContains(['join', [['o' => 'sales_order'], 'o.entity_id = i.order_id', []]], $activity);
        self::assertContains(['where', ['i.parent_item_id IS NULL']], $activity, 'a configurable sale counts once');
        self::assertContains(['where', ['o.state <> ?', 'canceled']], $activity);
        self::assertContains(['where', ['i.store_id IN (S1)']], $activity);
        self::assertContains(['where', ['i.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)']], $activity);
        self::assertContains(['group', ['i.product_id']], $activity);

        $ranked = $this->calls[3];
        self::assertSame(['a' => 'S2'], array_map('strval', $ranked[0][1][0]));
        self::assertSame('RANK() OVER (ORDER BY a.amount DESC)', (string) $ranked[0][1][1]['activity_rank']);
        self::assertContains(['where', ['a.amount > 0']], $ranked, 'products without sales get no rank');
    }

    public function testEachWindowHasItsOwnAlias(): void
    {
        $join = $this->condition(Bestseller::class, 'sold_last_7d', '>=', '1', ['resource' => $this->resource()])
            ->getTablesToJoin();

        self::assertSame(['ol_sold_7'], array_keys($join));
        self::assertContains(['where', ['i.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)']], $this->calls[2]);
    }

    public function testMostViewedCountsProductViewEventsOfTheWindow(): void
    {
        $join = $this->condition(MostViewed::class, 'view_rank_30d', '<=', '10', ['resource' => $this->resource()])
            ->getTablesToJoin();

        self::assertSame(['ol_viewed_30'], array_keys($join));
        $activity = $this->calls[2];
        self::assertSame(['v' => 'report_event'], $activity[0][1][0]);
        self::assertSame('object_id', $activity[0][1][1]['product_id']);
        self::assertContains(['where', ['v.event_type_id = ?', 1]], $activity);
        self::assertContains(['where', ['v.store_id IN (S1)']], $activity);
        self::assertContains(['where', ['v.logged_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)']], $activity);
        self::assertContains(['group', ['v.object_id']], $activity);
    }

    public function testWithoutTheReportsTableNothingMatchesAndTheSqlStaysValid(): void
    {
        $this->reportsInstalled = false;

        $join = $this->condition(MostViewed::class, 'views_last_7d', '>=', '1', ['resource' => $this->resource()])
            ->getTablesToJoin();

        self::assertSame('(S2)', (string) $join['ol_viewed_7']['name']);
        $empty = $this->calls[2];
        self::assertSame(['none' => 'store'], $empty[0][1][0]);
        self::assertSame(['product_id', 'amount', 'activity_rank'], array_keys($empty[0][1][1]));
        self::assertContains(['where', ['1 = 0']], $empty);
    }

    private function resource(): ResourceConnection
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(fn (): Select => $this->recordingSelect());
        $connection->method('isTableExists')->willReturnCallback(fn (): bool => $this->reportsInstalled);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $resource;
    }

    private function recordingSelect(): Select
    {
        $number = count($this->calls) + 1;
        $this->calls[$number] = [];
        $select = $this->createStub(Select::class);
        foreach (['from', 'join', 'where', 'group'] as $method) {
            $select->method($method)->willReturnCallback(function (mixed ...$args) use ($number, $method, &$select): Select {
                while ($args !== [] && end($args) === null) {
                    array_pop($args); // defaults the stub fills in
                }
                $this->calls[$number][] = [$method, $args];

                return $select;
            });
        }
        $select->method('__toString')->willReturn('S' . $number);

        return $select;
    }
}
