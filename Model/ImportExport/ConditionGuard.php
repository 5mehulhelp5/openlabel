<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

use Iranimij\OpenLabel\Model\Condition\ConditionPool;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Magento\Rule\Model\Condition\AbstractCondition;

/**
 * An imported condition tree names PHP classes that the rule engine instantiates, so only OpenLabel's own condition
 * classes and the ones registered in the condition pool are accepted (the same rule as the admin form).
 */
class ConditionGuard
{
    private const ALLOWED_NAMESPACE = 'Iranimij\\OpenLabel\\Model\\';

    /**
     * @param ConditionPool $conditionPool
     */
    public function __construct(private readonly ConditionPool $conditionPool)
    {
    }

    /**
     * @param array<string, mixed> $tree decoded `conditions_serialized`
     * @return string[]
     */
    public function errors(array $tree): array
    {
        if ($tree === []) {
            return [];
        }
        $rootType = (string) ($tree['type'] ?? '');
        if (!$this->isAllowed($rootType) || !is_a($rootType, Combine::class, true)) {
            return ['the condition tree must start with an OpenLabel "all/any" group.'];
        }

        return $this->walk($tree['conditions'] ?? []);
    }

    /**
     * @param mixed $conditions
     * @return string[]
     */
    private function walk(mixed $conditions): array
    {
        $errors = [];
        foreach (is_array($conditions) ? $conditions : [] as $condition) {
            $type = is_array($condition) ? (string) ($condition['type'] ?? '') : '';
            if (!$this->isAllowed($type)) {
                $errors[] = sprintf('condition type "%s" is not an OpenLabel condition.', $type);
                continue;
            }
            $errors = array_merge($errors, $this->walk($condition['conditions'] ?? []));
        }

        return $errors;
    }

    /**
     * @param string $type
     * @return bool
     */
    private function isAllowed(string $type): bool
    {
        if ($type === '' || !class_exists($type) || !is_subclass_of($type, AbstractCondition::class)) {
            return false;
        }

        return str_starts_with($type, self::ALLOWED_NAMESPACE)
            || in_array($type, $this->conditionPool->getTypes(), true);
    }
}
