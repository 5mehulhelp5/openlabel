<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Rule;

use Iranimij\OpenLabel\Model\Condition\AbstractBuiltIn;
use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Sql\Builder;

/**
 * The core rule SQL builder, used by OpenLabel's rule model only, with two changes:
 *
 * - When it recurses into a nested condition group, the core implementation starts a new list of tables to join and
 *   drops the ones collected before. OpenLabel keeps its quick switches at the root and the advanced tree as a
 *   nested group, so the switches' joins (price index, stock, reviews) were lost and saving such a label failed.
 * - OpenLabel's built-in conditions get their SQL here, "(guard AND field <operator> value)", so missing data never
 *   matches (whatever the operator) and the result is the same on every Magento version (some wrap numeric fields
 *   in IFNULL(field, 0)). Product attribute conditions keep the core behaviour.
 */
class SqlBuilder extends Builder
{
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
            return (string) parent::_getMappedSqlCondition($condition, (string) $value, (bool) $isDefaultStoreUsed);
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
}
