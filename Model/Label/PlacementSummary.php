<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Api\Data\PlacementInterface;

/**
 * Turns "listing:tr,product:tl" (as aggregated by the grid collection) into "Listing TR, Product TL".
 */
class PlacementSummary
{
    /**
     * @param string|null $raw comma-separated area:position pairs in sort order
     * @return string
     */
    public function format(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return (string) __('Nowhere yet');
        }
        $parts = [];
        foreach (explode(',', $raw) as $pair) {
            [$area, $position] = array_pad(explode(':', $pair, 2), 2, '');
            // Item lists and emails have no position: the labels show with the item.
            $parts[] = in_array($area, PlacementInterface::ITEM_AREAS, true)
                ? $this->areaLabel($area)
                : $this->areaLabel($area) . ' ' . $this->positionLabel($position);
        }

        return implode(', ', $parts);
    }

    /**
     * @param string $position
     * @return string short words for the positions added in 1.1, the code for the 3×3 grid (TL, BR, ...)
     */
    private function positionLabel(string $position): string
    {
        return match ($position) {
            'above_l' => (string) __('above left'),
            'above_c' => (string) __('above centre'),
            'above_r' => (string) __('above right'),
            'below_l' => (string) __('below left'),
            'below_c' => (string) __('below centre'),
            'below_r' => (string) __('below right'),
            'left_t', 'left_m', 'left_b' => (string) __('left side'),
            'right_t', 'right_m', 'right_b' => (string) __('right side'),
            'near_price' => (string) __('next to the price'),
            'near_cart' => (string) __('next to Add to Cart'),
            'under_name' => (string) __('under the name'),
            default => strtoupper($position),
        };
    }

    /**
     * @param string $area
     * @return string
     */
    private function areaLabel(string $area): string
    {
        return match ($area) {
            'listing' => (string) __('Listing'),
            'product' => (string) __('Product'),
            'cart' => (string) __('Cart'),
            'minicart' => (string) __('Mini cart'),
            'wishlist' => (string) __('Wish list'),
            'compare' => (string) __('Compare'),
            'email' => (string) __('Emails'),
            default => ucfirst($area),
        };
    }
}
