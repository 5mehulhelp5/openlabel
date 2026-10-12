<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Css;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;

/**
 * Turns a design (and a placement's offsets and gap) into one custom-property line for the per-store stylesheet
 * (10 · Front-end Review FE2). Mirrors the admin live preview's mapping so the shop looks like the preview.
 */
class DesignRules
{
    private const HEX = '/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/';
    private const DEFAULT_GAP = 4;

    /** Breakpoints of the per-size overrides (08 · UX Spec §2: phone, tablet, desktop). */
    private const MEDIA = [
        'sm' => '(max-width:639px)',
        'md' => '(min-width:640px) and (max-width:1023px)',
        'lg' => '(min-width:1024px)',
    ];

    /**
     * @param int $designId
     * @return string
     */
    public function designClass(int $designId): string
    {
        return 'ol-d-' . $designId;
    }

    /**
     * @param int $placementId
     * @return string
     */
    public function placementClass(int $placementId): string
    {
        return 'ol-p-' . $placementId;
    }

    /**
     * Classes of one label element: type, design, shape (not for images), entry animation and hover effect.
     *
     * @param DesignInterface $design
     * @return string[]
     */
    public function labelClasses(DesignInterface $design): array
    {
        $isImage = $design->getType() === DesignInterface::TYPE_IMAGE;
        $classes = ['ol-label', 'ol-label--' . $design->getType(), $this->designClass((int) $design->getDesignId())];
        if (!$isImage && (string) $design->getShape() !== '') {
            $classes[] = 'ol-shape-' . $design->getShape();
        }
        $animation = $design->getAnimation();
        if ($animation !== DesignInterface::ANIMATION_NONE && in_array($animation, DesignInterface::ANIMATIONS, true)) {
            $classes[] = 'ol-anim-' . $animation;
        }
        $hover = $design->getHoverEffect();
        if ($hover !== DesignInterface::HOVER_EFFECT_NONE && in_array($hover, DesignInterface::HOVER_EFFECTS, true)) {
            $classes[] = 'ol-hover-' . $hover;
        }

        return $classes;
    }

    /**
     * @param DesignInterface $design
     * @return string `.ol-d-<id>{...}`, followed by one media rule per breakpoint override, or an empty string
     *                for an unsaved design
     */
    public function forDesign(DesignInterface $design): string
    {
        if ($design->getDesignId() === null) {
            return '';
        }
        $props = [];
        foreach (['--ol-bg' => $design->getBgColor(), '--ol-fg' => $design->getTextColor(),
                     '--ol-border' => $design->getBorderColor()] as $name => $colour) {
            $colour = $this->colour($colour);
            if ($colour !== null) {
                $props[$name] = $colour;
            }
        }
        if ($design->getBorderWidth() > 0) {
            $props['--ol-bw'] = $design->getBorderWidth() . 'px';
        }
        $opacity = max(0, min(100, $design->getOpacity()));
        if ($opacity < 100) {
            $props['--ol-op'] = $this->number($opacity / 100);
        }
        if ($design->getRotation() !== 0) {
            $props['--ol-rot'] = $design->getRotation() . 'deg';
        }
        $props += $this->sizeProps($design, $design->getWidth(), $design->getFontSize());
        $selector = '.' . $this->designClass((int) $design->getDesignId());
        $rules = [$selector . '{' . $this->join($props) . '}'];
        $sizes = $design->getSizes();
        foreach (self::MEDIA as $breakpoint => $media) {
            $size = $sizes[$breakpoint] ?? [];
            $sizeProps = $this->sizeProps($design, (int) ($size['width'] ?? 0), (int) ($size['font_size'] ?? 0));
            if ($sizeProps !== []) {
                $rules[] = '@media ' . $media . '{' . $selector . '{' . $this->join($sizeProps) . '}}';
            }
        }

        return implode("\n", $rules);
    }

    /**
     * @param DesignInterface $design
     * @param int $width in the design's size mode, 0 = unset
     * @param int $fontSize px, 0 = unset
     * @return array<string, string>
     */
    private function sizeProps(DesignInterface $design, int $width, int $fontSize): array
    {
        $props = [];
        if ($width > 0) {
            $unit = $design->getSizeMode() === DesignInterface::SIZE_MODE_PX ? 'px' : 'cqw';
            $props['--ol-w'] = $width . $unit;
        }
        if ($fontSize > 0) {
            $props['--ol-fs-max'] = $fontSize . 'px';
        }

        return $props;
    }

    /**
     * @param PlacementInterface $placement
     * @return string|null `.ol-p-<id>{...}`, or null when the placement uses the structural defaults
     */
    public function forPlacement(PlacementInterface $placement): ?string
    {
        $props = [];
        if ($placement->getGap() !== self::DEFAULT_GAP) {
            $props['--ol-gap'] = max(0, $placement->getGap()) . 'px';
        }
        if ($placement->getOffsetX() !== 0) {
            $props['--ol-ox'] = $placement->getOffsetX() . 'px';
        }
        if ($placement->getOffsetY() !== 0) {
            $props['--ol-oy'] = $placement->getOffsetY() . 'px';
        }
        if ($props === [] || $placement->getPlacementId() === null) {
            return null;
        }

        return '.' . $this->placementClass((int) $placement->getPlacementId()) . '{' . $this->join($props) . '}';
    }

    /**
     * @param string|null $colour
     * @return string|null lower-case hex in its shortest form (#ffffff → #fff), or null for anything that is not a
     *                     hex colour
     */
    private function colour(?string $colour): ?string
    {
        $colour = strtolower(trim((string) $colour));
        if (preg_match(self::HEX, $colour) !== 1) {
            return null;
        }
        if (strlen($colour) === 7 || strlen($colour) === 9) {
            $pairs = str_split(substr($colour, 1), 2);
            $short = true;
            foreach ($pairs as $pair) {
                $short = $short && $pair[0] === $pair[1];
            }
            if ($short) {
                return '#' . implode('', array_map(static fn (string $pair): string => $pair[0], $pairs));
            }
        }

        return $colour;
    }

    /**
     * @param float $value
     * @return string
     */
    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, string> $props
     * @return string
     */
    private function join(array $props): string
    {
        $parts = [];
        foreach ($props as $name => $value) {
            $parts[] = $name . ':' . $value;
        }

        return implode(';', $parts);
    }
}
