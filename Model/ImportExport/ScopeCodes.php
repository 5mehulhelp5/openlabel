<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Store view and customer group ids ↔ codes, so an export file is portable between shops whose ids differ.
 */
class ScopeCodes
{
    /** @var array<int, string>|null */
    private ?array $stores = null;

    /** @var array<int, string>|null */
    private ?array $groups = null;

    /**
     * @param StoreRepositoryInterface $storeRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * Forget the cached lists (store views or groups created since).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->stores = null;
        $this->groups = null;
    }

    /**
     * @param int $storeId
     * @return string the code, or `#<id>` for a store view that no longer exists
     */
    public function storeCode(int $storeId): string
    {
        return $this->stores()[$storeId] ?? '#' . $storeId;
    }

    /**
     * @param string $code
     * @return int|null
     */
    public function storeId(string $code): ?int
    {
        $id = array_search($code, $this->stores(), true);

        return $id === false ? null : (int) $id;
    }

    /**
     * @param int $groupId
     * @return string the code, or `#<id>` for a group that no longer exists
     */
    public function groupCode(int $groupId): string
    {
        return $this->groups()[$groupId] ?? '#' . $groupId;
    }

    /**
     * @param string $code
     * @return int|null
     */
    public function groupId(string $code): ?int
    {
        $id = array_search($code, $this->groups(), true);

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array<int, string>
     */
    private function stores(): array
    {
        if ($this->stores === null) {
            $this->stores = [];
            foreach ($this->storeRepository->getList() as $store) {
                $this->stores[(int) $store->getId()] = (string) $store->getCode();
            }
        }

        return $this->stores;
    }

    /**
     * @return array<int, string>
     */
    private function groups(): array
    {
        if ($this->groups === null) {
            $this->groups = [];
            foreach ($this->groupRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $group) {
                $this->groups[(int) $group->getId()] = (string) $group->getCode();
            }
        }

        return $this->groups;
    }
}
