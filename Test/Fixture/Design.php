<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Fixture;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignInterfaceFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\TestFramework\Fixture\RevertibleDataFixtureInterface;

/**
 * Integration-test fixture: a text design. Pass 'store_texts' => [storeId => ['text' => .., 'alt_text' => ..]] to translate.
 */
class Design implements RevertibleDataFixtureInterface
{
    private const DEFAULTS = [
        'name' => 'Fixture design',
        'type' => DesignInterface::TYPE_TEXT,
        'shape' => 'pill',
        'bg_color' => '#e11d48',
        'text_color' => '#ffffff',
        'size_mode' => DesignInterface::SIZE_MODE_PERCENT,
        'width' => 18,
        'store_texts' => [0 => ['text' => 'Sale', 'alt_text' => null, 'tooltip' => null]],
    ];

    /**
     * @param DesignInterfaceFactory $designFactory
     * @param DesignRepositoryInterface $designRepository
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly DesignInterfaceFactory $designFactory,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(array $data = []): ?DataObject
    {
        $data = array_merge(self::DEFAULTS, $data);
        $design = $this->designFactory->create();
        $storeTexts = $data['store_texts'];
        unset($data['store_texts']);
        $design->setData($data);
        $design->setStoreTexts($storeTexts);

        return $this->designRepository->save($design);
    }

    /**
     * @inheritDoc
     */
    public function revert(DataObject $data): void
    {
        // A test that errored leaves its labels behind (the framework reverts fixtures before tearDown); remove them
        // so the real error is reported instead of an aborted run.
        $designId = (int) $data->getData('design_id');
        $connection = $this->resource->getConnection();
        $labelIds = $connection->fetchCol(
            $connection->select()->from($this->resource->getTableName('openlabel_label'), ['label_id'])
                ->where('design_id = ?', $designId)
        );
        if ($labelIds !== []) {
            $connection->delete($this->resource->getTableName('openlabel_index'), ['label_id IN (?)' => $labelIds]);
            $connection->delete($this->resource->getTableName('openlabel_label'), ['label_id IN (?)' => $labelIds]);
        }
        $this->designRepository->deleteById($designId);
    }
}
