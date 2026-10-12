<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterfaceFactory;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\LabelFactory;

/**
 * "Save & Duplicate": a disabled copy of a label with its conditions, scope, schedule and placements.
 */
class Duplicator
{
    /**
     * @param LabelFactory $labelFactory
     * @param PlacementInterfaceFactory $placementFactory
     * @param LabelRepositoryInterface $labelRepository
     */
    public function __construct(
        private readonly LabelFactory $labelFactory,
        private readonly PlacementInterfaceFactory $placementFactory,
        private readonly LabelRepositoryInterface $labelRepository
    ) {
    }

    /**
     * @param LabelInterface $source
     * @return LabelInterface the saved copy
     */
    public function duplicate(LabelInterface $source): LabelInterface
    {
        $copy = $this->labelFactory->create();
        $copy->setName((string) __('%1 (copy)', $source->getName()))
            ->setStatus(LabelInterface::STATUS_DISABLED)
            ->setPriority($source->getPriority())
            ->setStoreIds($source->getStoreIds())
            ->setCustomerGroupIds($source->getCustomerGroupIds())
            ->setValidFrom($source->getValidFrom())
            ->setValidTo($source->getValidTo())
            ->setConditionsSerialized($source->getConditionsSerialized())
            ->setApplyToParent($source->isApplyToParent())
            ->setHideLowerPriority($source->isHideLowerPriority())
            ->setHideOnZeroVariable($source->isHideOnZeroVariable())
            ->setClickUrl($source->getClickUrl())
            ->setClickNofollow($source->isClickNofollow());
        if ($source->getDesignId() !== null) {
            $copy->setDesignId($source->getDesignId());
        }
        $placements = [];
        foreach ($source->getPlacements() as $placement) {
            $placements[] = $this->placementFactory->create()
                ->setArea($placement->getArea())
                ->setPosition($placement->getPosition())
                ->setPinPhysicalSide($placement->isPinPhysicalSide())
                ->setDesignId($placement->getDesignId())
                ->setOffsetX($placement->getOffsetX())
                ->setOffsetY($placement->getOffsetY())
                ->setMaxLabels($placement->getMaxLabels())
                ->setStacking($placement->getStacking())
                ->setGap($placement->getGap());
        }
        $copy->setPlacements($placements);

        return $this->labelRepository->save($copy);
    }
}
