<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Rule;

use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\CombineFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection as ActionCollection;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;

/**
 * The condition tree of one label, evaluated in SQL against a product collection for one store,
 * website and (for price conditions) customer group. Not persisted: labels store the serialized tree.
 *
 * @method string|null getConditionsSerialized()
 * @method $this setConditionsSerialized(string $conditions)
 */
class Rule extends AbstractModel
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CombineFactory $combineFactory
     * @param ActionCollectionFactory $actionCollectionFactory
     * @param SqlBuilder $sqlBuilder
     * @param Json $serializer
     * @param array<string, mixed> $data
     * @param ExtensionAttributesFactory|null $extensionFactory
     * @param AttributeValueFactory|null $customAttributeFactory
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        private readonly CombineFactory $combineFactory,
        private readonly ActionCollectionFactory $actionCollectionFactory,
        private readonly SqlBuilder $sqlBuilder,
        Json $serializer,
        array $data = [],
        ?ExtensionAttributesFactory $extensionFactory = null,
        ?AttributeValueFactory $customAttributeFactory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            null,
            null,
            $data,
            $extensionFactory,
            $customAttributeFactory,
            $serializer
        );
    }

    /**
     * @inheritDoc
     */
    public function getConditionsInstance(): Combine
    {
        return $this->combineFactory->create();
    }

    /**
     * Labels have no actions; an empty core action collection keeps the parent model happy.
     *
     * @inheritDoc
     */
    public function getActionsInstance(): ActionCollection
    {
        return $this->actionCollectionFactory->create();
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return (int) $this->getData('store_id');
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }

    /**
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int) $this->getData('website_id');
    }

    /**
     * @return int|null null when the rule is not evaluated for one group
     */
    public function getCustomerGroupId(): ?int
    {
        $group = $this->getData('customer_group_id');

        return $group === null ? null : (int) $group;
    }

    /**
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId(int $websiteId): self
    {
        return $this->setData('website_id', $websiteId);
    }

    /**
     * @param int|null $customerGroupId
     * @return $this
     */
    public function setCustomerGroupId(?int $customerGroupId): self
    {
        return $this->setData('customer_group_id', $customerGroupId);
    }

    /**
     * Restrict the collection to the products matching the tree (06 · F4: matching happens in SQL).
     *
     * @param ProductCollection $collection
     * @return void
     */
    public function applyToCollection(ProductCollection $collection): void
    {
        /** @var Combine $conditions */
        $conditions = $this->getConditions();
        $conditions->collectValidatedAttributes($collection);
        $this->sqlBuilder->attachConditionToCollection($collection, $conditions);
    }

    /**
     * True when any condition depends on the customer group (price index), so the indexer writes one row per group.
     *
     * @return bool
     */
    public function requiresCustomerGroup(): bool
    {
        /** @var Combine $conditions */
        $conditions = $this->getConditions();

        return $conditions->requiresCustomerGroup();
    }

    /**
     * True when any condition changes with the passing of time (is-new windows, days since created).
     *
     * @return bool
     */
    public function isDateRelative(): bool
    {
        /** @var Combine $conditions */
        $conditions = $this->getConditions();

        return $conditions->isDateRelative();
    }
}
