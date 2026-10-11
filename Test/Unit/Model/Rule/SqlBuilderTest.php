<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Rule;

use Iranimij\OpenLabel\Model\Rule\SqlBuilder;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Sql\ExpressionFactory;
use PHPUnit\Framework\TestCase;

/**
 * The core builder forgets the joins of conditions it saw before a nested group (it restarts the list when it
 * recurses); quick switches at the root followed by the advanced tree then lost their joins ("Unknown column
 * ol_price.price" on save).
 */
class SqlBuilderTest extends TestCase
{
    public function testJoinsBeforeAndAfterANestedGroupAreAllKept(): void
    {
        $root = $this->combine([
            $this->leaf(['ol_price' => ['name' => 'catalog_product_index_price', 'condition' => 'p']]),
            $this->combine([
                $this->leaf(['ol_review' => ['name' => 'review_entity_summary', 'condition' => 'r']]),
                $this->combine([$this->leaf(['ol_deep' => ['name' => 'deep', 'condition' => 'd']])]),
            ]),
            $this->leaf(['ol_stock' => ['name' => 'cataloginventory_stock_status', 'condition' => 's']]),
        ]);

        self::assertSame(['ol_price', 'ol_review', 'ol_deep', 'ol_stock'], array_keys($this->tables($root)));
    }

    public function testTheFirstDefinitionOfAnAliasWins(): void
    {
        $root = $this->combine([
            $this->leaf(['ol_price' => ['name' => 'first', 'condition' => 'a']]),
            $this->combine([$this->leaf(['ol_price' => ['name' => 'second', 'condition' => 'b']])]),
        ]);

        self::assertSame('first', $this->tables($root)['ol_price']['name']);
    }

    public function testNoJoinsForAnEmptyTree(): void
    {
        self::assertSame([], $this->tables($this->combine([])));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function tables(Combine $root): array
    {
        $builder = new SqlBuilder(
            $this->createStub(ExpressionFactory::class),
            $this->createStub(AttributeRepositoryInterface::class)
        );
        $method = new \ReflectionMethod($builder, '_getCombineTablesToJoin');
        $method->setAccessible(true);

        return $method->invoke($builder, $root);
    }

    /**
     * @param AbstractCondition[] $children
     */
    private function combine(array $children): Combine
    {
        $combine = $this->createStub(Combine::class);
        $combine->method('getConditions')->willReturn($children);

        return $combine;
    }

    /**
     * @param array<string, array<string, string>> $tables
     */
    private function leaf(array $tables): AbstractCondition
    {
        // A condition without children; core conditions answer getConditions() through DataObject's __call.
        return new class ($tables) extends AbstractCondition {
            /**
             * @param array<string, array<string, string>> $tables
             */
            public function __construct(private readonly array $tables) // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            {
            }

            /**
             * @return array<string, array<string, string>>
             */
            public function getTablesToJoin()
            {
                return $this->tables;
            }
        };
    }
}
