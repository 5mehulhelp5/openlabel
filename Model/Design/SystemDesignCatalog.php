<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Design;

use Iranimij\OpenLabel\Api\Data\DesignInterface;

/**
 * The 31 built-in designs (15 since 1.0, 16 added in 1.1). Our own definitions; every text/background pair passes WCAG AA (4.5:1).
 * Designs are matched by name with is_system = 1, so names must never change once released.
 */
class SystemDesignCatalog
{
    public const SALE = 'sale_pill';
    public const NEW = 'new_pill';
    public const LOW_STOCK = 'low_stock_pill';

    /**
     * @return array<string, array<string, mixed>> keyed by catalog key
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->rows() as $key => $row) {
            [$name, $type, $shape, $bg, $fg, $border, $text] = $row;
            $motion = $row[7] ?? [];
            $definitions[$key] = [
                DesignInterface::NAME => $name,
                DesignInterface::TYPE => $type,
                DesignInterface::SHAPE => $shape,
                DesignInterface::BG_COLOR => $bg,
                DesignInterface::TEXT_COLOR => $fg,
                DesignInterface::BORDER_COLOR => $border,
                DesignInterface::BORDER_WIDTH => $border === null ? 0 : 2,
                DesignInterface::FONT_SIZE => 14,
                DesignInterface::SIZE_MODE => DesignInterface::SIZE_MODE_PERCENT,
                DesignInterface::WIDTH => $shape === 'circle' ? 16 : 24,
                DesignInterface::OPACITY => 100,
                DesignInterface::ROTATION => $shape === 'corner' ? -45 : 0,
                DesignInterface::ANIMATION => $motion[DesignInterface::ANIMATION] ?? DesignInterface::ANIMATION_NONE,
                DesignInterface::HOVER_EFFECT => $motion[DesignInterface::HOVER_EFFECT] ?? DesignInterface::HOVER_EFFECT_NONE,
                DesignInterface::IS_SYSTEM => true,
                DesignInterface::STORE_TEXTS => [
                    DesignInterface::DEFAULT_STORE_ID => ['text' => $text, 'alt_text' => null, 'tooltip' => null],
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Name, type, shape, background, text colour, border colour, default text, optionally motion.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string, 6: string, 7?: array<string, string>}>
     */
    private function rows(): array
    {
        return [
            self::SALE => ['Sale pill (red)', 'shape', 'pill', '#b91c1c', '#ffffff', null, 'Sale -{SAVE_PERCENT}%'],
            self::NEW => ['New pill (green)', 'shape', 'pill', '#15803d', '#ffffff', null, 'New'],
            self::LOW_STOCK => ['Low stock pill (amber)', 'shape', 'pill', '#fbbf24', '#1f2937', null,
                'Only {STOCK_QTY} left'],
            'sold_out' => ['Sold out (grey)', 'shape', 'rectangle', '#374151', '#ffffff', null, 'Sold out'],
            'sale_ribbon' => ['Sale ribbon (rose)', 'shape', 'ribbon', '#be123c', '#ffffff', null, 'Sale'],
            'new_corner' => ['New corner (teal)', 'shape', 'corner', '#0f766e', '#ffffff', null, 'New'],
            'percent_circle' => ['Discount circle (red)', 'shape', 'circle', '#c81e1e', '#ffffff', null,
                '-{SAVE_PERCENT}%'],
            'save_amount' => ['Save amount (rust)', 'shape', 'rectangle', '#9a3412', '#ffffff', null,
                'Save {SAVE_AMOUNT}'],
            'black_friday' => ['Black Friday (black and yellow)', 'shape', 'rectangle', '#111827', '#facc15', null,
                'Black Friday'],
            'limited_outline' => ['Limited outline (red)', 'shape', 'pill', '#ffffff', '#b91c1c', '#b91c1c',
                'Limited'],
            'exclusive' => ['Exclusive (navy)', 'shape', 'rectangle', '#1e3a8a', '#ffffff', null, 'Exclusive'],
            'top_rated' => ['Top rated (violet)', 'shape', 'pill', '#6d28d9', '#ffffff', null, 'Top rated'],
            'rating' => ['Rating (cream)', 'shape', 'pill', '#fef3c7', '#78350f', null, '★ {RATING}'],
            'eco' => ['Eco (forest)', 'text', 'rectangle', '#166534', '#ffffff', null, 'Eco'],
            'free_shipping' => ['Free shipping (blue)', 'text', 'rectangle', '#1d4ed8', '#ffffff', null,
                'Free shipping'],
            // 1.1
            'bestseller' => ['Bestseller (bronze)', 'shape', 'pill', '#854d0e', '#ffffff', null, 'Bestseller',
                [DesignInterface::ANIMATION => 'fade']],
            'trending' => ['Trending (magenta)', 'shape', 'pill', '#a21caf', '#ffffff', null, 'Trending',
                [DesignInterface::HOVER_EFFECT => 'grow']],
            'popular' => ['Popular (indigo)', 'shape', 'rectangle', '#4338ca', '#ffffff', null, 'Popular'],
            'ships_free' => ['Ships free (sky)', 'shape', 'ribbon', '#075985', '#ffffff', null, 'Ships free'],
            'back_in_stock' => ['Back in stock (emerald)', 'shape', 'pill', '#047857', '#ffffff', null,
                'Back in stock'],
            'pre_order' => ['Pre-order (slate)', 'shape', 'rectangle', '#334155', '#ffffff', null, 'Pre-order'],
            'last_chance' => ['Last chance (orange outline)', 'shape', 'pill', '#ffffff', '#9a3412', '#9a3412',
                'Last chance'],
            'brand' => ['Brand tag (charcoal)', 'text', 'rectangle', '#1f2937', '#ffffff', null, '{BRAND}'],
            'category' => ['Category tag (stone)', 'text', 'rectangle', '#f5f5f4', '#292524', null,
                'New in {CATEGORY_NAME}'],
            'gift' => ['Gift idea (berry)', 'shape', 'ribbon', '#9f1239', '#ffffff', null, 'Gift idea'],
            'organic' => ['Organic (olive)', 'text', 'rectangle', '#3f6212', '#ffffff', null, 'Organic'],
            'handmade' => ['Handmade (clay)', 'shape', 'pill', '#7c2d12', '#ffffff', null, 'Handmade'],
            'vegan' => ['Vegan (green outline)', 'shape', 'pill', '#ffffff', '#166534', '#166534', 'Vegan'],
            'clearance' => ['Clearance (yellow)', 'shape', 'corner', '#fde047', '#1c1917', null, 'Clearance'],
            'flash_sale' => ['Flash sale (red, pops in)', 'shape', 'circle', '#991b1b', '#ffffff', null,
                '-{SAVE_PERCENT}%', [DesignInterface::ANIMATION => 'pop']],
            'online_only' => ['Online only (teal)', 'shape', 'rectangle', '#115e59', '#ffffff', null, 'Online only'],
        ];
    }
}
