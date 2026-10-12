<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Setup\Patch\Data;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Model\DesignFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\Design\SystemDesignCatalog;
use Iranimij\OpenLabel\Model\ResourceModel\Design\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Installs the built-in designs of the catalog. Idempotent: a system design that already exists (matched by name) is never
 * touched, so merchant edits survive; a missing one is created again.
 */
class SystemDesigns implements DataPatchInterface
{
    /**
     * @param SystemDesignCatalog $catalog
     * @param DesignFactory $designFactory
     * @param DesignRepositoryInterface $designRepository
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly SystemDesignCatalog $catalog,
        private readonly DesignFactory $designFactory,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $existing = $this->collectionFactory->create()
            ->addFieldToFilter(DesignInterface::IS_SYSTEM, ['eq' => 1])
            ->getColumnValues(DesignInterface::NAME);

        foreach ($this->catalog->getDefinitions() as $definition) {
            if (in_array($definition[DesignInterface::NAME], $existing, true)) {
                continue;
            }
            $storeTexts = $definition[DesignInterface::STORE_TEXTS];
            unset($definition[DesignInterface::STORE_TEXTS]);
            $design = $this->designFactory->create();
            $design->setData($definition);
            $design->setStoreTexts($storeTexts);
            $this->designRepository->save($design);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
