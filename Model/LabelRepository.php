<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\LabelSearchResultsInterface;
use Iranimij\OpenLabel\Api\Data\LabelSearchResultsInterfaceFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Label\Validator;
use Iranimij\OpenLabel\Model\Validation\References;
use Iranimij\OpenLabel\Model\ResourceModel\Label as LabelResource;
use Iranimij\OpenLabel\Model\ResourceModel\Label\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResultFactory;

class LabelRepository implements LabelRepositoryInterface
{
    /**
     * @param LabelResource $resource
     * @param LabelFactory $labelFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param LabelSearchResultsInterfaceFactory $searchResultsFactory
     * @param Validator $validator
     * @param ValidationResultFactory $validationResultFactory
     * @param DesignRepositoryInterface $designRepository
     * @param References $references
     */
    public function __construct(
        private readonly LabelResource $resource,
        private readonly LabelFactory $labelFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly LabelSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly Validator $validator,
        private readonly ValidationResultFactory $validationResultFactory,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly References $references
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(LabelInterface $label): LabelInterface
    {
        $errors = array_merge($this->validator->validate($label), $this->references->forLabel($label));
        if ($errors !== []) {
            throw new ValidationException(
                __('The label could not be saved.'),
                null,
                0,
                $this->validationResultFactory->create(['errors' => $errors])
            );
        }
        try {
            $this->designRepository->getById((int) $label->getDesignId());
        } catch (NoSuchEntityException $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
        if (!$label instanceof Label) {
            throw new CouldNotSaveException(__('Unsupported label implementation %1.', get_debug_type($label)));
        }
        $this->resource->beginTransaction();
        try {
            $this->resource->save($label);
            $this->resource->savePlacements($label);
            $this->resource->commit();
        } catch (\Exception $e) {
            $this->resource->rollBack();
            throw new CouldNotSaveException(__('The label could not be saved: %1', $e->getMessage()), $e);
        }

        return $label;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $labelId): LabelInterface
    {
        $label = $this->labelFactory->create();
        $this->resource->load($label, $labelId);
        if ($label->getLabelId() === null) {
            throw new NoSuchEntityException(__('Label with ID "%1" does not exist.', $labelId));
        }
        $this->resource->loadPlacements($label);

        return $label;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LabelSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $collection->addPlacements();

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function delete(LabelInterface $label): bool
    {
        if (!$label instanceof Label) {
            throw new CouldNotDeleteException(__('Unsupported label implementation %1.', get_debug_type($label)));
        }
        try {
            $this->resource->delete($label);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('The label could not be deleted: %1', $e->getMessage()), $e);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $labelId): bool
    {
        return $this->delete($this->getById($labelId));
    }
}
