<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Design;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Magento\Framework\Phrase;

/**
 * Validation rules from the Design entity spec. Messages say what to do (08 · UX Spec §6).
 */
class Validator
{
    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';
    private const PERCENT_RANGE = [1, 100];
    private const PX_RANGE = [8, 600];
    private const FONT_SIZE_RANGE = [8, 72];
    private const SIZE_FIELDS = ['width', 'font_size'];

    /**
     * @param DesignInterface $design
     * @return Phrase[] empty when the design is valid
     */
    public function validate(DesignInterface $design): array
    {
        $errors = [];
        if (trim($design->getName()) === '') {
            $errors[] = __('Enter a name for the design.');
        }
        $type = $design->getType();
        if (!in_array($type, DesignInterface::TYPES, true)) {
            $errors[] = __('Design type must be text, image or shape.');
        }
        if ($type === DesignInterface::TYPE_TEXT && trim((string) $design->getText()) === '') {
            $errors[] = __('Enter the label text for the default store view.');
        }
        if ($type === DesignInterface::TYPE_IMAGE) {
            if ($design->getImagePath() === null) {
                $errors[] = __('Upload an image for this design.');
            }
            if (trim((string) $design->getAltText()) === '') {
                $errors[] = __('Enter alternative text for the image.');
            }
        }
        if ($type === DesignInterface::TYPE_SHAPE && !in_array($design->getShape(), DesignInterface::SHAPES, true)) {
            $errors[] = __('Shape must be rectangle, circle, ribbon, corner or pill.');
        }
        foreach ([$design->getBgColor(), $design->getTextColor(), $design->getBorderColor()] as $colour) {
            if ($colour !== null && !preg_match(self::HEX_PATTERN, $colour)) {
                $errors[] = __('Colours must be hex values such as #e11d48.');
                break;
            }
        }
        $sizeMode = $design->getSizeMode();
        if ($sizeMode === DesignInterface::SIZE_MODE_PERCENT) {
            if (!$this->inRange($design->getWidth(), self::PERCENT_RANGE)) {
                $errors[] = __('Width must be between 1 and 100 percent.');
            }
        } elseif ($sizeMode === DesignInterface::SIZE_MODE_PX) {
            if (!$this->inRange($design->getWidth(), self::PX_RANGE)) {
                $errors[] = __('Width must be between 8 and 600 px.');
            }
        } else {
            $errors[] = __('Size mode must be percent or px.');
        }
        if (!$this->inRange($design->getOpacity(), [0, 100])) {
            $errors[] = __('Opacity must be between 0 and 100.');
        }
        if (!in_array($design->getAnimation(), DesignInterface::ANIMATIONS, true)) {
            $errors[] = __('Entry animation must be none, fade or pop.');
        }
        if (!in_array($design->getHoverEffect(), DesignInterface::HOVER_EFFECTS, true)) {
            $errors[] = __('Hover effect must be none, grow or lift.');
        }

        return array_merge($errors, $this->validateSizes($design));
    }

    /**
     * Per-breakpoint overrides follow the same ranges as the design's own size.
     *
     * @param DesignInterface $design
     * @return Phrase[]
     */
    private function validateSizes(DesignInterface $design): array
    {
        $errors = [];
        $percent = $design->getSizeMode() === DesignInterface::SIZE_MODE_PERCENT;
        foreach ($design->getSizes() as $breakpoint => $values) {
            if (!in_array($breakpoint, DesignInterface::BREAKPOINTS, true)) {
                $errors[] = __('Size overrides exist for sm, md and lg only.');
                continue;
            }
            if (array_diff(array_keys($values), self::SIZE_FIELDS) !== []) {
                $errors[] = __('A size override holds width and font size only.');
                continue;
            }
            if (isset($values['width'])) {
                if ($percent && !$this->inRange($values['width'], self::PERCENT_RANGE)) {
                    $errors[] = __('Width must be between 1 and 100 percent.');
                } elseif (!$percent && !$this->inRange($values['width'], self::PX_RANGE)) {
                    $errors[] = __('Width must be between 8 and 600 px.');
                }
            }
            if (isset($values['font_size']) && !$this->inRange($values['font_size'], self::FONT_SIZE_RANGE)) {
                $errors[] = __('Font size must be between 8 and 72 px.');
            }
        }

        return array_values(array_unique($errors));
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
