<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Rule;

use Iranimij\OpenLabel\Model\Condition\ConditionPool;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\CombineFactory;
use Iranimij\OpenLabel\Model\Rule\Condition\Product;
use Iranimij\OpenLabel\Model\Rule\Condition\ProductFactory;
use Iranimij\OpenLabel\Model\Rule\Rule;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Rule\Model\Action\Collection as ActionCollection;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;
use Iranimij\OpenLabel\Model\Rule\SqlBuilder as Builder;
use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase
{
    private ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
    }

    public function testContextSettersAndGetters(): void
    {
        $rule = $this->rule($this->combine());

        $rule->setStoreId(2)->setWebsiteId(3)->setCustomerGroupId(4);

        self::assertSame(2, $rule->getStoreId());
        self::assertSame(3, $rule->getWebsiteId());
        self::assertSame(4, $rule->getCustomerGroupId());
        self::assertNull($rule->setCustomerGroupId(null)->getCustomerGroupId());
    }

    public function testApplyToCollectionCollectsAttributesAndAttachesTheSql(): void
    {
        $combine = $this->combine();
        $collection = $this->createStub(Collection::class);
        $builder = $this->createMock(Builder::class);
        $builder->expects(self::once())->method('attachConditionToCollection')->with($collection, $combine);
        $rule = $this->rule($combine, $builder);

        $rule->applyToCollection($collection);
    }

    public function testDelegatesGroupAndDateQuestionsToTheTree(): void
    {
        $combine = $this->combine();
        $rule = $this->rule($combine);
        self::assertFalse($rule->requiresCustomerGroup());
        self::assertFalse($rule->isDateRelative());

        $combine->addCondition($this->objectManager->getObject(OnSale::class));
        $combine->addCondition($this->objectManager->getObject(IsNew::class));
        self::assertTrue($rule->requiresCustomerGroup());
        self::assertTrue($rule->isDateRelative());
        self::assertInstanceOf(ActionCollection::class, $rule->getActionsInstance());
    }

    private function rule(Combine $combine, ?Builder $builder = null): Rule
    {
        $combineFactory = $this->createStub(CombineFactory::class);
        $combineFactory->method('create')->willReturn($combine);
        $actionFactory = $this->createStub(ActionCollectionFactory::class);
        $actionFactory->method('create')->willReturn($this->createStub(ActionCollection::class));
        /** @var Rule $rule */
        $rule = $this->objectManager->getObject(Rule::class, [
            'combineFactory' => $combineFactory,
            'actionCollectionFactory' => $actionFactory,
            'sqlBuilder' => $builder ?? $this->createStub(Builder::class),
            'extensionFactory' => $this->createStub(ExtensionAttributesFactory::class),
            'customAttributeFactory' => $this->createStub(AttributeValueFactory::class),
        ]);

        return $rule;
    }

    private function combine(): Combine
    {
        $productFactory = $this->createStub(ProductFactory::class);
        $productFactory->method('create')->willReturn($this->createStub(Product::class));
        /** @var Combine $combine */
        $combine = $this->objectManager->getObject(Combine::class, [
            'productConditionFactory' => $productFactory,
            'conditionPool' => $this->createStub(ConditionPool::class),
        ]);

        return $combine;
    }
}
