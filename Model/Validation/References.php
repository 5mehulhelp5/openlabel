<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Validation;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Store views and customer groups a label or design refers to must exist. The admin forms only offer existing ones;
 * the REST API accepts any ID, and an unknown one would otherwise hide a label silently or fail on a foreign key.
 */
class References
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param LabelInterface $label
     * @return Phrase[] empty when every reference exists
     */
    public function forLabel(LabelInterface $label): array
    {
        $errors = $this->unknownStores($label->getStoreIds());
        if ($label->getCustomerGroupIds() !== []) {
            $connection = $this->resource->getConnection();
            $known = array_map('intval', $connection->fetchCol(
                $connection->select()->from($this->resource->getTableName('customer_group'), ['customer_group_id'])
            ));
            foreach (array_diff($label->getCustomerGroupIds(), $known) as $groupId) {
                $errors[] = __('Customer group %1 does not exist.', $groupId);
            }
        }

        return $errors;
    }

    /**
     * @param DesignInterface $design
     * @return Phrase[] empty when every store view of the texts exists (0 = all store views)
     */
    public function forDesign(DesignInterface $design): array
    {
        return $this->unknownStores(array_keys($design->getStoreTexts()));
    }

    /**
     * @param int[] $storeIds
     * @return Phrase[]
     */
    private function unknownStores(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }
        $known = array_map(static fn ($store): int => (int) $store->getId(), $this->storeManager->getStores(true));
        $errors = [];
        foreach (array_diff(array_map('intval', $storeIds), $known) as $storeId) {
            $errors[] = __('Store view %1 does not exist.', $storeId);
        }

        return $errors;
    }
}
