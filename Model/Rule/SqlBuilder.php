<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Rule;

use Iranimij\OpenLabel\Model\Condition\AbstractBuiltIn;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Sql\Builder;

/**
 * The core rule SQL builder, used by OpenLabel's rule model only, with two changes:
 *
 * - When it recurses into a nested condition group, the core implementation starts a new list of tables to join and
 *   drops the ones collected before. OpenLabel keeps its quick switches at the root and the advanced tree as a
 *   nested group, so the switches' joins (price index, stock, reviews) were lost and saving such a label failed.
 * - Every condition gets its SQL here, so it means the same on every Magento version (2.4.7 and 2.4.8 wrap each
 *   field in IFNULL(field, 0), 2.4.9 does not): missing data never matches, whatever the operator. Built-ins add
 *   a guard, "(guard AND field <operator> value)"; product attribute conditions keep the core operators.
 * - A built-in may join a bounded subquery (e.g. units sold in the last 30 days) by giving a Zend_Db_Expr as the
 *   table "name"; the core builder only takes table names.
 */
class SqlBuilder extends Builder
{
    private const NATIVE_OPERATORS = [
        '==' => '= ?',
        '!=' => '<> ?',
        '>=' => '>= ?',
        '>' => '> ?',
        '<=' => '<= ?',
        '<' => '< ?',
        '{}' => 'IN (?)',
        '!{}' => 'NOT IN (?)',
        '()' => 'IN (?)',
        '!()' => 'NOT IN (?)',
    ];

    private const OPERATORS = [
        '==' => '= ?',
        '!=' => '<> ?',
        '>=' => '>= ?',
        '>' => '> ?',
        '<=' => '<= ?',
        '<' => '< ?',
        '()' => 'IN (?)',
        '!()' => 'NOT IN (?)',
    ];

    /**
     * @param mixed $condition
     * @param mixed $value " NOT " when the surrounding group is "is FALSE"
     * @param mixed $isDefaultStoreUsed
     * @return string
     */
    protected function _getMappedSqlCondition($condition, $value = '', $isDefaultStoreUsed = true): string // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if (!$condition instanceof AbstractBuiltIn) {
            return $this->nativeCondition($condition, (string) $value);
        }
        $operator = (string) $condition->getOperatorForValidate();
        $bind = $condition->getBindArgumentValue();
        if (!isset(self::OPERATORS[$operator]) || $bind === null || $bind === '' || $bind === []) {
            return (string) $value . '(1 = 0)';
        }
        if (in_array($operator, ['()', '!()'], true) && !is_array($bind)) {
            $bind = preg_split('#\s*[,;]\s*#', (string) $bind, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if ($this->_connection === null) {
            throw new \LogicException('attachConditionToCollection() sets the connection before conditions are mapped.');
        }
        $sql = $this->_connection->quoteInto('(' . $condition->getMappedSqlField() . ') ' . self::OPERATORS[$operator], $bind);
        $guard = $condition->getSqlGuard();

        return (string) $value . ($guard === null ? '(' . $sql . ')' : '((' . $guard . ') AND ' . $sql . ')');
    }

    /**
     * @param AbstractCollection $collection
     * @param Combine $combine
     * @return Builder
     */
    protected function _joinTablesToCollection(AbstractCollection $collection, Combine $combine): Builder // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        foreach ($this->_getCombineTablesToJoin($combine) as $alias => $joinTable) {
            $name = $joinTable['name'];
            $collection->getSelect()->joinLeft(
                [$alias => $name instanceof \Zend_Db_Expr ? $name : $collection->getResource()->getTable($name)],
                $joinTable['condition'],
                $joinTable['columns'] ?? '*'
            );
        }

        return $this;
    }

    /**
     * @param Combine $combine
     * @param array<string, array<string, mixed>> $tables
     * @return array<string, array<string, mixed>>
     */
    protected function _getChildCombineTablesToJoin(Combine $combine, $tables = []) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        foreach ($combine->getConditions() as $condition) {
            if ($condition->getConditions()) {
                $tables = $this->_getChildCombineTablesToJoin($condition, $tables);
                continue;
            }
            foreach ($condition->getTablesToJoin() as $alias => $table) {
                if (!isset($tables[$alias])) {
                    $tables[$alias] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * Product attribute conditions (attribute, category, SKU…) with the core operators, but without the
     * IFNULL(field, 0) that Magento 2.4.7/2.4.8 put around every field: a product without a value never matches,
     * so "news from date on or before …" or "weight less than 1" do not pick up products that have no value, on
     * every version (2.4.9 behaves like this already).
     *
     * @param mixed $condition
     * @param string $prefix " NOT " when the surrounding group is "is FALSE"
     * @return string
     * @throws LocalizedException
     */
    private function nativeCondition(mixed $condition, string $prefix): string
    {
        if ($this->_connection === null) {
            throw new \LogicException('attachConditionToCollection() sets the connection before conditions are mapped.');
        }
        $field = $condition->getMappedSqlField();
        if (empty($field)) {
            return $prefix . '(1 = -1)';
        }
        if (!$field instanceof \Zend_Db_Expr && preg_match('/[^a-z0-9\-_\.\`]/i', (string) $field) > 0) {
            throw new LocalizedException(__('Invalid field'));
        }
        $operator = (string) $condition->getOperatorForValidate();
        if (!isset(self::NATIVE_OPERATORS[$operator])) {
            throw new LocalizedException(__('Unknown condition operator'));
        }
        $quotedField = (string) $this->_connection->quoteIdentifier($field);
        $bind = $condition->getBindArgumentValue();
        if ($condition->getInputType() === 'string' && in_array($operator, ['{}', '!{}'], true)) {
            $sql = $this->_connection->quoteInto(
                $quotedField . ($operator === '{}' ? ' LIKE ?' : ' NOT LIKE ?'),
                '%' . $bind . '%'
            );
        } else {
            $sql = $this->_connection->quoteInto($quotedField . ' ' . self::NATIVE_OPERATORS[$operator], $bind);
        }
        // Multiselect values may be stored comma separated: match each selected option inside them too.
        $attribute = $condition->getAttributeObject();
        if ($attribute && $attribute->getFrontendInput() === 'multiselect' && is_array($bind)
            && in_array($operator, ['()', '{}'], true)
        ) {
            foreach ($bind as $item) {
                $sql .= $this->_connection->quoteInto(' OR (FIND_IN_SET(?, ' . $quotedField . ') > 0)', $item);
            }
        }

        return $prefix . '(' . $sql . ')';
    }
}
