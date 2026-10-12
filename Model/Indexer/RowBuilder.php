<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Indexer;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Model\Rule\RuleFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Writes the index rows of one label (child rows per store and group, then parent rows) into a table.
 */
class RowBuilder
{
    private const BATCH = 1000;

    /**
     * @param ResourceConnection $resource
     * @param RuleFactory $ruleFactory
     * @param Matcher $matcher
     * @param GroupSelector $groupSelector
     * @param ParentRows $parentRows
     * @param StoreManagerInterface $storeManager
     * @param ManualAssignments $manualAssignments
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly RuleFactory $ruleFactory,
        private readonly Matcher $matcher,
        private readonly GroupSelector $groupSelector,
        private readonly ParentRows $parentRows,
        private readonly StoreManagerInterface $storeManager,
        private readonly ManualAssignments $manualAssignments
    ) {
    }

    /**
     * @param LabelInterface $label
     * @param string $table unprefixed target table (index or replica)
     * @param int[]|null $productIds restrict to these products (partial reindex); null = whole catalog
     * @return void
     */
    public function build(LabelInterface $label, string $table, ?array $productIds = null): void
    {
        $rule = $this->ruleFactory->create();
        $rule->setConditionsSerialized((string) $label->getConditionsSerialized());
        $groups = $this->groupSelector->forLabel($label, $rule->requiresCustomerGroup());
        $manual = $this->manualAssignments->forLabel((int) $label->getLabelId());
        if ($productIds !== null) {
            $manual = array_values(array_intersect($manual, array_map('intval', $productIds)));
        }
        $base = [
            'label_id' => (int) $label->getLabelId(),
            'priority' => $label->getPriority(),
            'valid_from' => $label->getValidFrom(),
            'valid_to' => $label->getValidTo(),
        ];
        foreach ($this->storeIds($label) as $storeId) {
            $assigned = $this->matcher->available($storeId, $manual);
            foreach ($groups as $groupId) {
                $matched = $this->matcher->match($rule, $storeId, $groupId, $productIds);
                // Products assigned by hand show the label whatever its conditions.
                $matched = array_values(array_unique(array_merge($matched, $assigned)));
                $rows = [];
                foreach ($matched as $productId) {
                    $rows[] = $base + [
                        'product_id' => $productId,
                        'store_id' => $storeId,
                        'customer_group_id' => $groupId,
                        'parent_product_id' => null,
                    ];
                }
                if ($label->isApplyToParent()) {
                    foreach ($this->parentRows->parentsOf($matched) as $parentId => $children) {
                        $rows[] = $base + [
                            'product_id' => $parentId,
                            'store_id' => $storeId,
                            'customer_group_id' => $groupId,
                            'parent_product_id' => min($children),
                        ];
                    }
                }
                $this->insert($table, $rows);
            }
        }
    }

    /**
     * @param LabelInterface $label
     * @return int[] active store view ids the label is limited to (all when the label has none)
     */
    private function storeIds(LabelInterface $label): array
    {
        $limit = $label->getStoreIds();
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if ($store->getIsActive() && ($limit === [] || in_array($storeId, $limit, true))) {
                $ids[] = $storeId;
            }
        }

        return $ids;
    }

    /**
     * @param string $table
     * @param array<int, array<string, mixed>> $rows
     * @return void
     */
    private function insert(string $table, array $rows): void
    {
        $connection = $this->resource->getConnection();
        foreach (array_chunk($rows, self::BATCH) as $chunk) {
            $connection->insertOnDuplicate($this->resource->getTableName($table), $chunk, ['parent_product_id', 'priority', 'valid_from', 'valid_to']);
        }
    }
}
