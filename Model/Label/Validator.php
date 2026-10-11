<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Magento\Framework\Phrase;

/**
 * Validation rules from the Label and Placement entity specs. Messages say what to do (08 · UX Spec §6).
 */
class Validator
{
    private const MAX_LABELS_RANGE = [1, 10];
    private const GAP_RANGE = [0, 64];
    private const OFFSET_RANGE = [-200, 200];
    private const CLICK_URL_MAX = 1024;

    /**
     * @param LabelInterface $label
     * @return Phrase[] empty when the label is valid
     */
    public function validate(LabelInterface $label): array
    {
        $errors = [];
        if (trim($label->getName()) === '') {
            $errors[] = __('Enter a name for the label.');
        }
        if ($label->getDesignId() === null) {
            $errors[] = __('Choose a design for the label.');
        }
        if ($label->getPlacements() === []) {
            $errors[] = __('Pick at least one placement so the label appears somewhere.');
        }
        $from = $label->getValidFrom();
        $to = $label->getValidTo();
        if ($from !== null && $to !== null && strtotime($to) <= strtotime($from)) {
            $errors[] = __('The end date must be after the start date.');
        }
        if (!$this->isSafeClickUrl($label->getClickUrl())) {
            $errors[] = __('Enter a link that starts with https://, http:// or /.');
        }
        foreach ($label->getPlacements() as $placement) {
            $errors = array_merge($errors, $this->validatePlacement($placement));
        }

        return $errors;
    }

    /**
     * @param PlacementInterface $placement
     * @return Phrase[]
     */
    private function validatePlacement(PlacementInterface $placement): array
    {
        $errors = [];
        if (!in_array($placement->getArea(), PlacementInterface::AREAS, true)) {
            $errors[] = __('Placement area "%1" is not available in this version.', $placement->getArea());
        }
        if (!in_array($placement->getPosition(), PlacementInterface::POSITIONS, true)) {
            $errors[] = __('Placement position "%1" is not available in this version.', $placement->getPosition());
        }
        $stackings = [PlacementInterface::STACKING_VERTICAL, PlacementInterface::STACKING_HORIZONTAL];
        if (!in_array($placement->getStacking(), $stackings, true)) {
            $errors[] = __('Stacking must be vertical or horizontal.');
        }
        if (!$this->inRange($placement->getMaxLabels(), self::MAX_LABELS_RANGE)) {
            $errors[] = __('Max labels must be between 1 and 10.');
        }
        if (!$this->inRange($placement->getGap(), self::GAP_RANGE)) {
            $errors[] = __('Gap must be between 0 and 64 px.');
        }
        if (!$this->inRange($placement->getOffsetX(), self::OFFSET_RANGE)
            || !$this->inRange($placement->getOffsetY(), self::OFFSET_RANGE)
        ) {
            $errors[] = __('Offsets must be between -200 and 200 px.');
        }

        return $errors;
    }

    /**
     * Only web links and same-shop paths: no javascript:, data: or protocol-relative "//host" links.
     *
     * @param string|null $url
     * @return bool
     */
    private function isSafeClickUrl(?string $url): bool
    {
        if ($url === null) {
            return true;
        }
        if (strlen($url) > self::CLICK_URL_MAX) {
            return false;
        }

        return (bool) preg_match('#^(?:https?://[^\s/]+(?:/\S*)?|/(?!/)\S*)$#i', $url);
    }

    /**
     * @param int $value
     * @param array{int, int} $range
     * @return bool
     */
    private function inRange(int $value, array $range): bool
    {
        return $value >= $range[0] && $value <= $range[1];
    }
}
