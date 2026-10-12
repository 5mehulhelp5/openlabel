<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\FreeShipping;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection as CartRuleCollection;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;

class FreeShippingTest extends ConditionTestCase
{
    public function testProductsReachingTheMinimumOrderAmountShipFree(): void
    {
        $condition = $this->freeShipping(true, '100', []);

        self::assertTrue($condition->validate($this->product(['price' => 150, 'final_price' => 120])));
        self::assertFalse($condition->validate($this->product(['price' => 150, 'final_price' => 99.99])));
        self::assertSame('(CASE WHEN ol_price.final_price >= 100.000000 THEN 1 ELSE 0 END)', (string) $condition->getMappedSqlField());
    }

    public function testNothingShipsFreeWhenTheMethodIsOffOrHasNoMinimum(): void
    {
        self::assertSame('0', (string) $this->freeShipping(false, '100', [])->getMappedSqlField());
        self::assertSame('0', (string) $this->freeShipping(true, '', [])->getMappedSqlField());
        self::assertFalse($this->freeShipping(false, '100', [])->validate($this->product(['price' => 500, 'final_price' => 500])));
    }

    public function testACartRuleWithoutConditionsMakesEveryProductShipFree(): void
    {
        $condition = $this->freeShipping(false, '', [$this->rule(1, null, null)]);

        self::assertSame('1', (string) $condition->getMappedSqlField());
        self::assertTrue($condition->validate($this->product(['price' => 5, 'final_price' => 5])));
    }

    public function testCartRulesWithConditionsOrWithoutFreeShippingDoNotCount(): void
    {
        $withConditions = '{"type":"x","conditions":[{"type":"y","attribute":"base_subtotal","operator":">=","value":"50"}]}';
        $condition = $this->freeShipping(false, '', [
            $this->rule(1, $withConditions, null),
            $this->rule(1, null, $withConditions),
            $this->rule(0, null, null),
            $this->rule(2, '{"type":"x","conditions":[]}', 'not json'),
        ]);

        self::assertSame('0', (string) $condition->getMappedSqlField());
    }

    public function testShipsFreeIsAYesNoConditionPerCustomerGroupRefreshedDaily(): void
    {
        $condition = $this->freeShipping(true, '100', []);

        self::assertSame('select', $condition->getInputType());
        self::assertTrue($condition->requiresCustomerGroup());
        self::assertTrue($condition->isDateRelative());
    }

    /**
     * @param DataObject[] $rules
     */
    private function freeShipping(bool $active, string $subtotal, array $rules): FreeShipping
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturn($active);
        $config->method('getValue')->willReturn($subtotal);
        $collection = $this->createStub(CartRuleCollection::class);
        $collection->method('addWebsiteGroupDateFilter')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rules));
        $factory = $this->createStub(CartRuleCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        /** @var FreeShipping $condition */
        $condition = $this->condition(FreeShipping::class, 'free_shipping', '==', '1', [
            'scopeConfig' => $config,
            'cartRules' => $factory,
            'json' => new Json(),
        ]);

        return $condition;
    }

    private function rule(int $freeShipping, ?string $conditions, ?string $actions): DataObject
    {
        return new DataObject([
            'simple_free_shipping' => $freeShipping,
            'conditions_serialized' => $conditions,
            'actions_serialized' => $actions,
        ]);
    }
}
