<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Resolver;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;

class ResolvedLabel implements ResolvedLabelInterface
{
    /**
     * @param int $labelId
     * @param string $name
     * @param int $priority
     * @param bool $hideLowerPriority
     * @param int $productId
     * @param int|null $parentProductId
     * @param PlacementInterface $placement
     * @param DesignInterface $design
     * @param bool $hideOnZeroVariable
     */
    public function __construct(
        private readonly int $labelId,
        private readonly string $name,
        private readonly int $priority,
        private readonly bool $hideLowerPriority,
        private readonly int $productId,
        private readonly ?int $parentProductId,
        private readonly PlacementInterface $placement,
        private readonly DesignInterface $design,
        private readonly bool $hideOnZeroVariable = false
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLabelId(): int
    {
        return $this->labelId;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritDoc
     */
    public function isHideLowerPriority(): bool
    {
        return $this->hideLowerPriority;
    }

    /**
     * @inheritDoc
     */
    public function isHideOnZeroVariable(): bool
    {
        return $this->hideOnZeroVariable;
    }

    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @inheritDoc
     */
    public function getParentProductId(): ?int
    {
        return $this->parentProductId;
    }

    /**
     * @inheritDoc
     */
    public function getPlacement(): PlacementInterface
    {
        return $this->placement;
    }

    /**
     * @inheritDoc
     */
    public function getDesign(): DesignInterface
    {
        return $this->design;
    }

    /**
     * @inheritDoc
     */
    public function getText(): ?string
    {
        return $this->design->getText();
    }

    /**
     * @inheritDoc
     */
    public function getAltText(): ?string
    {
        return $this->design->getAltText();
    }
}
