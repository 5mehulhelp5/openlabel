<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Condition\Stock\LegacyStockData;
use Iranimij\OpenLabel\Model\Condition\Stock\MsiStockData;
use Iranimij\OpenLabel\Model\Condition\Stock\StockDataInterface;
use Iranimij\OpenLabel\Model\Condition\Stock\StockDataResolver;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class StockTest extends ConditionTestCase
{
    public function testIsSalableAsksTheStockData(): void
    {
        $stock = $this->stockData(salable: true, qty: 3.0);

        self::assertTrue($this->stock('is_salable', '==', '1', $stock)->validate($this->product(['sku' => 'A'])));
        self::assertFalse($this->stock('is_salable', '==', '0', $stock)->validate($this->product(['sku' => 'A'])));
    }

    public function testSalableQtyComparesNumerically(): void
    {
        $stock = $this->stockData(salable: true, qty: 3.0);

        self::assertTrue($this->stock('salable_qty', '<=', '5', $stock)->validate($this->product(['sku' => 'A'])));
        self::assertFalse($this->stock('salable_qty', '>', '5', $stock)->validate($this->product(['sku' => 'A'])));
    }

    public function testCompositeProductsNeverMatchQuantityConditions(): void
    {
        $stock = $this->stockData(salable: true, qty: 0.0);

        self::assertFalse($this->stock('salable_qty', '<=', '5', $stock)->validate($this->product(['sku' => 'C', 'type_id' => 'configurable'])));
        self::assertTrue($this->stock('is_salable', '==', '1', $stock)->validate($this->product(['sku' => 'C', 'type_id' => 'configurable'])));
    }

    public function testSqlJoinsTheStockTableOfTheRulesWebsite(): void
    {
        $stock = $this->stockData(salable: true, qty: 3.0);
        $condition = $this->stock('salable_qty', '<=', '5', $stock);

        $joins = $condition->getTablesToJoin();
        self::assertSame('inventory_stock_7', $joins['ol_stock']['name']);
        self::assertSame('ol_stock.sku = e.sku', $joins['ol_stock']['condition']);
        self::assertSame('ol_stock.quantity', (string) $condition->getMappedSqlField());
        self::assertSame(
            "e.type_id IN ('simple','virtual','downloadable') AND ol_stock.quantity IS NOT NULL",
            $condition->getSqlGuard(),
            'composites and products without a stock row never match a quantity, whatever the operator'
        );
        self::assertSame('ol_stock.is_salable', (string) $this->stock('is_salable', '==', '1', $stock)->getMappedSqlField());
        self::assertSame('ol_stock.is_salable IS NOT NULL', $this->stock('is_salable', '==', '1', $stock)->getSqlGuard());
        self::assertFalse($condition->requiresCustomerGroup());
    }

    public function testResolverPrefersMsiWhenItsModulesAreEnabled(): void
    {
        $manager = $this->createStub(Manager::class);
        $manager->method('isEnabled')->willReturn(true);
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $msi = $this->createStub(MsiStockData::class);
        $objectManager->expects(self::once())->method('get')->with(MsiStockData::class)->willReturn($msi);

        $resolver = new StockDataResolver($manager, $objectManager, $this->createStub(LegacyStockData::class));

        self::assertSame($msi, $resolver->get());
    }

    public function testResolverFallsBackToLegacyWithoutMsi(): void
    {
        $manager = $this->createStub(Manager::class);
        $manager->method('isEnabled')->willReturn(false);
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willThrowException(new \LogicException('must not be called'));
        $legacy = $this->createStub(LegacyStockData::class);

        $resolver = new StockDataResolver($manager, $objectManager, $legacy);

        self::assertSame($legacy, $resolver->get());
    }

    private function stock(string $attribute, string $operator, string $value, StockDataInterface $stockData): Stock
    {
        $resolver = $this->createStub(StockDataResolver::class);
        $resolver->method('get')->willReturn($stockData);
        /** @var Stock $condition */
        $condition = $this->condition(Stock::class, $attribute, $operator, $value, ['stockDataResolver' => $resolver]);

        return $condition;
    }

    private function stockData(bool $salable, float $qty): StockDataInterface
    {
        $stock = $this->createStub(StockDataInterface::class);
        $stock->method('isSalable')->willReturn($salable);
        $stock->method('getSalableQty')->willReturn($qty);
        $stock->method('getTableName')->willReturn('inventory_stock_7');
        $stock->method('getJoinCondition')->willReturn('ol_stock.sku = e.sku');
        $stock->method('getQtyColumn')->willReturn('quantity');
        $stock->method('getSalableColumn')->willReturn('is_salable');

        return $stock;
    }
}
