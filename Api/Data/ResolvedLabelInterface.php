<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Api\Data;

/**
 * One label to render on one product: the label, the placement it is shown in and the design to use,
 * with the text already resolved for the store view.
 *
 * @api
 */
interface ResolvedLabelInterface
{
    /**
     * @return int
     */
    public function getLabelId(): int;

    /**
     * @return string admin name, never rendered
     */
    public function getName(): string;

    /**
     * @return int 0 = highest
     */
    public function getPriority(): int;

    /**
     * @return bool
     */
    public function isHideLowerPriority(): bool;

    /**
     * @return bool leave the label out when a variable in its text is zero or empty
     */
    public function isHideOnZeroVariable(): bool;

    /**
     * @return int the product the label is shown on
     */
    public function getProductId(): int;

    /**
     * @return int|null the child that caused a parent row, null for direct matches
     */
    public function getParentProductId(): ?int;

    /**
     * @return PlacementInterface
     */
    public function getPlacement(): PlacementInterface;

    /**
     * The placement's design override or the label's design, store texts resolved for the request's store view.
     *
     * @return DesignInterface
     */
    public function getDesign(): DesignInterface;

    /**
     * @return string|null text for the store view, falling back to the default store
     */
    public function getText(): ?string;

    /**
     * @return string|null
     */
    public function getAltText(): ?string;
}
