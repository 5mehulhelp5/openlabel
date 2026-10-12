<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Indexer;

use Iranimij\OpenLabel\Model\Rule\Rule;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Runs one label's rule against the catalog for one store view and customer group: a single SQL query (06 · F4).
 */
class Matcher
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param Rule $rule
     * @param int $storeId
     * @param int $customerGroupId -1 when the rule has no price condition
     * @param int[]|null $productIds restrict to these products (partial reindex)
     * @return int[] matching enabled product ids assigned to the store's website
     */
    public function match(Rule $rule, int $storeId, int $customerGroupId, ?array $productIds = null): array
    {
        $store = $this->storeManager->getStore($storeId);
        $rule->setStoreId($storeId)->setWebsiteId((int) $store->getWebsiteId())->setCustomerGroupId($customerGroupId);
        $previousStore = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore($storeId);
        try {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId)->addStoreFilter($storeId);
            $collection->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED]);
            if ($productIds !== null) {
                $collection->addIdFilter(array_map('intval', $productIds));
            }
            $rule->applyToCollection($collection);

            return array_map('intval', $collection->getAllIds());
        } finally {
            $this->storeManager->setCurrentStore($previousStore);
        }
    }

    /**
     * Of the given products, those enabled and in the store (manual assignments skip the conditions, not these).
     *
     * @param int $storeId
     * @param int[] $productIds
     * @return int[]
     */
    public function available(int $storeId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED]);
        $collection->addIdFilter(array_map('intval', $productIds));

        return array_map('intval', $collection->getAllIds());
    }
}
