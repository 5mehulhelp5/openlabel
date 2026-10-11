<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Rule;

use Iranimij\OpenLabel\Model\Condition\AbstractBuiltIn;
use Iranimij\OpenLabel\Model\Rule\SqlBuilder;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Rule\Model\Condition\Sql\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @dataProvider builtInSql
     */
    #[DataProvider('builtInSql')]
    public function testBuiltInSql(?string $guard, string $operator, string $value, string $prefix, string $expected): void
    {
        self::assertSame($expected, $this->sql($this->builtIn($guard, $operator, $value), $prefix));
    }

    /**
     * @return array<string, array{?string, string, string, string, string}>
     */
    public static function builtInSql(): array
    {
        return [
            'guard and comparison' => ['g.id IS NOT NULL', '>=', '5', '', "((g.id IS NOT NULL) AND (x.field) >= '5')"],
            'no guard' => [null, '==', '0', '', "((x.field) = '0')"],
            'is not' => [null, '!=', '3', '', "((x.field) <> '3')"],
            'greater' => [null, '>', '3', '', "((x.field) > '3')"],
            'less or equal' => [null, '<=', '3', '', "((x.field) <= '3')"],
            'less' => [null, '<', '3', '', "((x.field) < '3')"],
            'is one of' => [null, '()', '1, 2', '', "((x.field) IN ('1', '2'))"],
            'is not one of' => ['g', '!()', '1;2', '', "((g) AND (x.field) NOT IN ('1', '2'))"],
            'unknown operator never matches' => [null, '<=>', '1', '', '(1 = 0)'],
            'empty value never matches' => [null, '==', '', '', '(1 = 0)'],
            'group "is FALSE" prefix' => [null, '==', '1', ' NOT ', " NOT ((x.field) = '1')"],
        ];
    }

    private function sql(AbstractBuiltIn $condition, string $prefix): string
    {
        $builder = new SqlBuilder($this->createStub(ExpressionFactory::class), $this->createStub(AttributeRepositoryInterface::class));
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(static function (string $text, mixed $value): string {
            $quoted = is_array($value)
                ? implode(', ', array_map(static fn ($v): string => "'" . $v . "'", $value))
                : "'" . $value . "'";

            return str_replace('?', $quoted, $text);
        });
        $property = new \ReflectionProperty(Builder::class, '_connection');
        $property->setAccessible(true);
        $property->setValue($builder, $connection);
        $method = new \ReflectionMethod($builder, '_getMappedSqlCondition');
        $method->setAccessible(true);

        return $method->invoke($builder, $condition, $prefix, true);
    }

    private function builtIn(?string $guard, string $operator, string $value): AbstractBuiltIn
    {
        $condition = new class ($guard) extends AbstractBuiltIn {
            public function __construct(private readonly ?string $guard) // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            {
            }

            /**
             * @return array<string, string>
             */
            protected function attributeOptions(): array
            {
                return ['field' => 'Field'];
            }

            protected function productValue(\Magento\Catalog\Model\Product $product): mixed
            {
                return null;
            }

            /**
             * @return \Zend_Db_Expr
             */
            public function getMappedSqlField()
            {
                return new \Zend_Db_Expr('x.field');
            }

            public function getSqlGuard(): ?string
            {
                return $this->guard;
            }
        };
        $condition->setData(['attribute' => 'field', 'operator' => $operator, 'value' => $value]);

        return $condition;
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
