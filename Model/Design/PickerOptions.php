<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Design;

use Iranimij\OpenLabel\Model\ResourceModel\Design\Grid\CollectionFactory;

/**
 * Designs for the visual picker in the label form: id, name, thumbnail and the data the live preview needs.
 * Built-in designs come first.
 */
class PickerOptions
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param Thumbnail $thumbnail
     * @param ImageUrl $imageUrl
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Thumbnail $thumbnail,
        private readonly ImageUrl $imageUrl
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('is_system', 'DESC')->setOrder('main_table.design_id', 'ASC');
        $options = [];
        foreach ($collection->getData() as $row) {
            $imageUrl = $this->imageUrl->get($row['image_path'] ?? null);
            $options[] = [
                'value' => (string) $row['design_id'],
                'label' => (string) $row['name'],
                'thumb' => $this->thumbnail->render([
                    'type' => $row['type'] ?? null,
                    'shape' => $row['shape'] ?? null,
                    'bg_color' => $row['bg_color'] ?? null,
                    'text_color' => $row['text_color'] ?? null,
                    'border_color' => $row['border_color'] ?? null,
                    'text' => $row['text'] ?? '',
                    'alt_text' => $row['alt_text'] ?? '',
                    'image_url' => $imageUrl,
                ]),
                'system' => (bool) $row['is_system'],
                'design' => [
                    'type' => $row['type'] ?? 'text',
                    'shape' => $row['shape'] ?? null,
                    'bg_color' => $row['bg_color'] ?? null,
                    'text_color' => $row['text_color'] ?? null,
                    'border_color' => $row['border_color'] ?? null,
                    'border_width' => (int) ($row['border_width'] ?? 0),
                    'font_size' => (int) ($row['font_size'] ?? 14),
                    'size_mode' => $row['size_mode'] ?? 'percent',
                    'width' => (int) ($row['width'] ?? 18),
                    'opacity' => (int) ($row['opacity'] ?? 100),
                    'rotation' => (int) ($row['rotation'] ?? 0),
                    'animation' => (string) ($row['animation'] ?? 'none'),
                    'hover_effect' => (string) ($row['hover_effect'] ?? 'none'),
                    'text' => (string) ($row['text'] ?? ''),
                    'alt_text' => (string) ($row['alt_text'] ?? ''),
                    'image_url' => $imageUrl,
                    'image_width' => isset($row['image_width']) ? (int) $row['image_width'] : null,
                    'image_height' => isset($row['image_height']) ? (int) $row['image_height'] : null,
                ],
            ];
        }

        return $options;
    }
}
