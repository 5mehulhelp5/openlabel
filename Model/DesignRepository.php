<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignSearchResultsInterface;
use Iranimij\OpenLabel\Api\Data\DesignSearchResultsInterfaceFactory;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\Design\Validator;
use Iranimij\OpenLabel\Model\Validation\References;
use Iranimij\OpenLabel\Model\ResourceModel\Design as DesignResource;
use Iranimij\OpenLabel\Model\ResourceModel\Design\CollectionFactory;
use Iranimij\OpenLabel\Model\ResourceModel\Label\CollectionFactory as LabelCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResultFactory;

class DesignRepository implements DesignRepositoryInterface
{
    /**
     * @param DesignResource $resource
     * @param DesignFactory $designFactory
     * @param CollectionFactory $collectionFactory
     * @param LabelCollectionFactory $labelCollectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param DesignSearchResultsInterfaceFactory $searchResultsFactory
     * @param Validator $validator
     * @param ValidationResultFactory $validationResultFactory
     * @param References $references
     */
    public function __construct(
        private readonly DesignResource $resource,
        private readonly DesignFactory $designFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LabelCollectionFactory $labelCollectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly DesignSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly Validator $validator,
        private readonly ValidationResultFactory $validationResultFactory,
        private readonly References $references
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(DesignInterface $design): DesignInterface
    {
        $errors = array_merge($this->validator->validate($design), $this->references->forDesign($design));
        if ($errors !== []) {
            throw new ValidationException(
                __('The design could not be saved.'),
                null,
                0,
                $this->validationResultFactory->create(['errors' => $errors])
            );
        }
        if (!$design instanceof Design) {
            throw new CouldNotSaveException(__('Unsupported design implementation %1.', get_debug_type($design)));
        }
        try {
            $this->resource->save($design);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The design could not be saved: %1', $e->getMessage()), $e);
        }

        return $design;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $designId): DesignInterface
    {
        $design = $this->designFactory->create();
        $this->resource->load($design, $designId);
        if ($design->getDesignId() === null) {
            throw new NoSuchEntityException(__('Design with ID "%1" does not exist.', $designId));
        }

        return $design;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): DesignSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function delete(DesignInterface $design): bool
    {
        if (!$design instanceof Design) {
            throw new CouldNotDeleteException(__('Unsupported design implementation %1.', get_debug_type($design)));
        }
        $labels = $this->labelCollectionFactory->create()
            ->addFieldToFilter(LabelInterface::DESIGN_ID, ['eq' => (int) $design->getDesignId()])
            ->getSize();
        if ($labels > 0) {
            throw new CouldNotDeleteException(
                __('The design "%1" is used by %2 label(s). Change their design first.', $design->getName(), $labels)
            );
        }
        try {
            $this->resource->delete($design);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('The design could not be deleted: %1', $e->getMessage()), $e);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $designId): bool
    {
        return $this->delete($this->getById($designId));
    }
}
