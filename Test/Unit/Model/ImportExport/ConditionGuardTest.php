<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\ImportExport;

use Iranimij\OpenLabel\Model\Condition\ConditionPool;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\ImportExport\ConditionGuard;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product;
use Magento\Framework\DataObject;
use Magento\Rule\Model\Condition\Combine as CoreCombine;
use PHPUnit\Framework\TestCase;

class ConditionGuardTest extends TestCase
{
    public function testOpenLabelConditionsAreAllowedAtAnyDepth(): void
    {
        $tree = ['type' => Combine::class, 'conditions' => [
            ['type' => Product::class, 'attribute' => 'sku'],
            ['type' => Combine::class, 'conditions' => [['type' => OnSale::class]]],
        ]];

        self::assertSame([], $this->guard()->errors($tree));
    }

    public function testConditionsRegisteredInThePoolAreAllowed(): void
    {
        $tree = ['type' => Combine::class, 'conditions' => [['type' => CoreCombine::class]]];

        self::assertSame([], $this->guard([CoreCombine::class])->errors($tree));
    }

    public function testOtherClassesAreRejected(): void
    {
        $tree = ['type' => Combine::class, 'conditions' => [
            ['type' => DataObject::class],
            ['type' => 'Not\\A\\Class'],
            ['type' => CoreCombine::class],
        ]];

        self::assertSame([
            'condition type "Magento\Framework\DataObject" is not an OpenLabel condition.',
            'condition type "Not\A\Class" is not an OpenLabel condition.',
            'condition type "Magento\Rule\Model\Condition\Combine" is not an OpenLabel condition.',
        ], $this->guard()->errors($tree));
    }

    public function testTheRootMustBeACombine(): void
    {
        self::assertSame(
            ['the condition tree must start with an OpenLabel "all/any" group.'],
            $this->guard()->errors(['type' => Product::class])
        );
    }

    public function testEmptyTreeIsFine(): void
    {
        self::assertSame([], $this->guard()->errors([]));
    }

    /**
     * @param string[] $poolTypes
     */
    private function guard(array $poolTypes = []): ConditionGuard
    {
        $pool = $this->createStub(ConditionPool::class);
        $pool->method('getTypes')->willReturn($poolTypes);

        return new ConditionGuard($pool);
    }
}
