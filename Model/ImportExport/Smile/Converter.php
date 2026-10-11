<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport\Smile;

use Iranimij\OpenLabel\Model\ImportExport\FileFormat;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product;

/**
 * Turns labels of the open-source Smile product label module (an image shown when a product has one attribute
 * option) into an OpenLabel export file, which the regular importer then checks and writes. Each Smile label becomes
 * an image design plus a label with the condition "attribute is option" and a placement per page it was shown on.
 */
class Converter
{
    public const MEDIA_DIR = 'smile_productlabel/imagelabel';

    private const POSITIONS = [
        'top-left' => 'tl',
        'top-right' => 'tr',
        'lower-left' => 'bl',
        'lower-right' => 'br',
    ];
    private const DISPLAY_PRODUCT = '1';
    private const DISPLAY_LISTING = '2';
    private const PRIORITY = 10;
    private const WIDTH_PERCENT = 18;

    /**
     * @param array<int, array<string, mixed>> $rows Smile labels with `attribute_code`, `store_codes` and
     *                                              `image_contents` resolved (see Reader)
     * @return array{file: array<string, mixed>, warnings: string[]}
     */
    public function convert(array $rows): array
    {
        $designs = [];
        $labels = [];
        $warnings = [];
        foreach ($rows as $row) {
            $where = sprintf('Smile label #%d "%s"', (int) $row['product_label_id'], (string) $row['name']);
            if ($row['attribute_code'] === null || $row['attribute_code'] === '') {
                $warnings[] = $where . ': its attribute no longer exists, skipped.';
                continue;
            }
            if ($row['image_contents'] === null) {
                $warnings[] = sprintf('%s: image "%s" not found in media/%s, skipped.', $where, (string) $row['image'], self::MEDIA_DIR);
                continue;
            }
            $placements = $this->placements($row, $where, $warnings);
            if ($placements === []) {
                $warnings[] = $where . ': shown nowhere (no listing or product page), skipped.';
                continue;
            }
            $key = FileFormat::DESIGN_PREFIX . 'smile-' . (int) $row['product_label_id'];
            $designs[] = [
                'key' => $key,
                'name' => 'Smile: ' . $row['name'],
                'type' => 'image',
                'size_mode' => 'percent',
                'width' => self::WIDTH_PERCENT,
                'store_texts' => ['admin' => ['text' => null, 'alt_text' => $row['alt'] ?: $row['name'], 'tooltip' => null]],
                'image' => [
                    'file' => (string) $row['image'],
                    'width' => null,
                    'height' => null,
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction -- same shape as an export file
                    'content' => base64_encode((string) $row['image_contents']),
                ],
            ];
            $labels[] = [
                'name' => (string) $row['name'],
                'status' => (int) $row['is_active'] === 1 ? 1 : 0,
                'priority' => self::PRIORITY,
                'stores' => array_values($row['store_codes']),
                'customer_groups' => [],
                'valid_from' => null,
                'valid_to' => null,
                'conditions' => [
                    'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [[
                        'type' => Product::class,
                        'attribute' => (string) $row['attribute_code'],
                        'operator' => '==',
                        'value' => (string) $row['option_id'],
                    ]],
                ],
                'design' => $key,
                'apply_to_parent' => false,
                'hide_lower_priority' => false,
                'placements' => $placements,
            ];
        }

        return [
            'file' => ['format' => FileFormat::NAME, 'version' => FileFormat::VERSION, 'designs' => $designs, 'labels' => $labels],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param string $where
     * @param string[] $warnings
     * @return array<int, array{area: string, position: string, sort_order: int}>
     */
    private function placements(array $row, string $where, array &$warnings): array
    {
        $display = array_map('trim', explode(',', (string) $row['display_on']));
        $placements = [];
        foreach ([
            self::DISPLAY_LISTING => ['listing', 'position_category_list', 'listings'],
            self::DISPLAY_PRODUCT => ['product', 'position_product_view', 'the product page'],
        ] as $flag => [$area, $column, $page]) {
            if (!in_array((string) $flag, $display, true)) {
                continue;
            }
            $smilePosition = (string) $row[$column];
            $position = self::POSITIONS[$smilePosition] ?? null;
            if ($position === null) {
                $warnings[] = sprintf('%s: unknown position "%s" on %s, using top left.', $where, $smilePosition, $page);
                $position = 'tl';
            }
            $placements[] = ['area' => $area, 'position' => $position, 'sort_order' => count($placements)];
        }

        return $placements;
    }
}
