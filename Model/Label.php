<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Model\ResourceModel\Label as LabelResource;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class Label extends AbstractModel implements LabelInterface, IdentityInterface
{
    /** Cache tag for every block that rendered any label (admin grid, collections). */
    public const CACHE_TAG = 'openlabel_label';

    /** Prefix of the per-label cache tag: openlabel_<label_id>. */
    public const CACHE_TAG_PREFIX = 'openlabel_';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'openlabel_label';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(LabelResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG];
        if ($this->getLabelId() !== null) {
            $identities[] = self::CACHE_TAG_PREFIX . $this->getLabelId();
        }

        return $identities;
    }

    /**
     * @inheritDoc
     */
    public function getLabelId(): ?int
    {
        return $this->getData(self::LABEL_ID) === null ? null : (int) $this->getData(self::LABEL_ID);
    }

    /**
     * @inheritDoc
     */
    public function setLabelId(int $labelId): LabelInterface
    {
        return $this->setData(self::LABEL_ID, $labelId);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): LabelInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return $this->getData(self::STATUS) === null ? self::STATUS_ENABLED : (int) $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(int $status): LabelInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    /**
     * @inheritDoc
     */
    public function setPriority(int $priority): LabelInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritDoc
     */
    public function getStoreIds(): array
    {
        return $this->toIdList($this->getData(self::STORE_IDS));
    }

    /**
     * @inheritDoc
     */
    public function setStoreIds(array $storeIds): LabelInterface
    {
        return $this->setData(self::STORE_IDS, array_values(array_map('intval', $storeIds)));
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroupIds(): array
    {
        return $this->toIdList($this->getData(self::CUSTOMER_GROUP_IDS));
    }

    /**
     * @inheritDoc
     */
    public function setCustomerGroupIds(array $customerGroupIds): LabelInterface
    {
        return $this->setData(self::CUSTOMER_GROUP_IDS, array_values(array_map('intval', $customerGroupIds)));
    }

    /**
     * @inheritDoc
     */
    public function getValidFrom(): ?string
    {
        return $this->nullableString(self::VALID_FROM);
    }

    /**
     * @inheritDoc
     */
    public function setValidFrom(?string $validFrom): LabelInterface
    {
        return $this->setData(self::VALID_FROM, $validFrom);
    }

    /**
     * @inheritDoc
     */
    public function getValidTo(): ?string
    {
        return $this->nullableString(self::VALID_TO);
    }

    /**
     * @inheritDoc
     */
    public function setValidTo(?string $validTo): LabelInterface
    {
        return $this->setData(self::VALID_TO, $validTo);
    }

    /**
     * @inheritDoc
     */
    public function getConditionsSerialized(): ?string
    {
        return $this->nullableString(self::CONDITIONS_SERIALIZED);
    }

    /**
     * @inheritDoc
     */
    public function setConditionsSerialized(?string $conditions): LabelInterface
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditions);
    }

    /**
     * @inheritDoc
     */
    public function getDesignId(): ?int
    {
        $id = $this->getData(self::DESIGN_ID);

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function setDesignId(int $designId): LabelInterface
    {
        return $this->setData(self::DESIGN_ID, $designId);
    }

    /**
     * @inheritDoc
     */
    public function isApplyToParent(): bool
    {
        return (bool) $this->getData(self::APPLY_TO_PARENT);
    }

    /**
     * @inheritDoc
     */
    public function setApplyToParent(bool $applyToParent): LabelInterface
    {
        return $this->setData(self::APPLY_TO_PARENT, $applyToParent);
    }

    /**
     * @inheritDoc
     */
    public function isHideLowerPriority(): bool
    {
        return (bool) $this->getData(self::HIDE_LOWER_PRIORITY);
    }

    /**
     * @inheritDoc
     */
    public function setHideLowerPriority(bool $hideLowerPriority): LabelInterface
    {
        return $this->setData(self::HIDE_LOWER_PRIORITY, $hideLowerPriority);
    }

    /**
     * @inheritDoc
     */
    public function isHideOnZeroVariable(): bool
    {
        return (bool) $this->getData(self::HIDE_ON_ZERO_VARIABLE);
    }

    /**
     * @inheritDoc
     */
    public function setHideOnZeroVariable(bool $hide): LabelInterface
    {
        return $this->setData(self::HIDE_ON_ZERO_VARIABLE, $hide);
    }

    /**
     * @inheritDoc
     */
    public function getClickUrl(): ?string
    {
        $url = trim((string) $this->getData(self::CLICK_URL));

        return $url === '' ? null : $url;
    }

    /**
     * @inheritDoc
     */
    public function setClickUrl(?string $url): LabelInterface
    {
        $url = trim((string) $url);

        return $this->setData(self::CLICK_URL, $url === '' ? null : $url);
    }

    /**
     * @inheritDoc
     */
    public function isClickNofollow(): bool
    {
        return (bool) $this->getData(self::CLICK_NOFOLLOW);
    }

    /**
     * @inheritDoc
     */
    public function setClickNofollow(bool $nofollow): LabelInterface
    {
        return $this->setData(self::CLICK_NOFOLLOW, $nofollow);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->nullableString(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->nullableString(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(?string $createdAt): self
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(?string $updatedAt): self
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPlacements(): array
    {
        $placements = $this->getData(self::PLACEMENTS);

        return is_array($placements) ? array_values($placements) : [];
    }

    /**
     * @inheritDoc
     */
    public function setPlacements(array $placements): LabelInterface
    {
        return $this->setData(self::PLACEMENTS, array_values($placements));
    }

    /**
     * CSV or array to a list of ints; empty means "all".
     *
     * @param mixed $value
     * @return int[]
     */
    private function toIdList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        return array_values(array_map('intval', array_filter(explode(',', (string) $value), static fn (string $v): bool => $v !== '')));
    }

    /**
     * @param string $key
     * @return string|null
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->getData($key);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
