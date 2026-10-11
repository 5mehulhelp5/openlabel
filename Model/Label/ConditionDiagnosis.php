<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Model\Indexer\Matcher;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\RuleFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rule\Model\Condition\AbstractCondition;

/**
 * "Why does my label match no products?": every condition of a label on its own, with the number of products it
 * matches in a store view. A condition that matches nothing alone, or conditions that contradict each other
 * (e.g. "on sale" and "out of stock" while out-of-stock products have no price), explain an empty label.
 */
class ConditionDiagnosis
{
    /**
     * @param RuleFactory $ruleFactory
     * @param Matcher $matcher
     * @param Json $json
     */
    public function __construct(
        private readonly RuleFactory $ruleFactory,
        private readonly Matcher $matcher,
        private readonly Json $json
    ) {
    }

    /**
     * @param LabelInterface $label
     * @param int $storeId
     * @return array<int, array{text: string, count: int, level: int}> top-level conditions, then the conditions of a
     *         nested group right after it (level 1)
     */
    public function diagnose(LabelInterface $label, int $storeId): array
    {
        $serialized = (string) $label->getConditionsSerialized();
        if ($serialized === '') {
            return [];
        }
        $groups = $label->getCustomerGroupIds();
        $group = $groups === [] ? 0 : (int) reset($groups);
        $rule = $this->ruleFactory->create();
        $rule->setConditionsSerialized($serialized);
        $rows = [];
        foreach ($rule->getConditions()->getConditions() as $condition) {
            $rows[] = $this->row($condition, $storeId, $group, 0);
            if ($condition instanceof Combine) {
                foreach ($condition->getConditions() as $child) {
                    $rows[] = $this->row($child, $storeId, $group, 1);
                }
            }
        }

        return $rows;
    }

    /**
     * @param AbstractCondition $condition
     * @param int $storeId
     * @param int $group
     * @param int $level
     * @return array{text: string, count: int, level: int}
     */
    private function row(AbstractCondition $condition, int $storeId, int $group, int $level): array
    {
        $rule = $this->ruleFactory->create();
        $rule->setConditionsSerialized($this->json->serialize([
            'type' => Combine::class,
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [$condition->asArray()],
        ]));

        return [
            'text' => trim(strip_tags((string) $condition->asString())),
            'count' => count($this->matcher->match($rule, $storeId, $group)),
            'level' => $level,
        ];
    }
}
