<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\ImportExport\Smile;

use Iranimij\OpenLabel\Model\ImportExport\FileFormat;
use Iranimij\OpenLabel\Model\ImportExport\Smile\Converter;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    public function testOneSmileLabelBecomesAnImageDesignAndALabel(): void
    {
        $result = (new Converter())->convert([$this->row()]);

        self::assertSame([], $result['warnings']);
        self::assertSame([], (new FileFormat())->errors($result['file']));
        self::assertSame([[
            'key' => 'design:smile-7',
            'name' => 'Smile: Eco',
            'type' => 'image',
            'size_mode' => 'percent',
            'width' => 18,
            'store_texts' => ['admin' => ['text' => null, 'alt_text' => 'Eco friendly', 'tooltip' => null]],
            'image' => ['file' => 'eco.png', 'width' => null, 'height' => null, 'content' => base64_encode('PNGDATA')],
        ]], $result['file']['designs']);
        self::assertSame([[
            'name' => 'Eco',
            'status' => 1,
            'priority' => 10,
            'stores' => ['default'],
            'customer_groups' => [],
            'valid_from' => null,
            'valid_to' => null,
            'conditions' => [
                'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                    ['type' => Product::class, 'attribute' => 'eco_collection', 'operator' => '==', 'value' => '42'],
                ],
            ],
            'design' => 'design:smile-7',
            'apply_to_parent' => false,
            'hide_lower_priority' => false,
            'placements' => [
                ['area' => 'listing', 'position' => 'tl', 'sort_order' => 0],
                ['area' => 'product', 'position' => 'br', 'sort_order' => 1],
            ],
        ]], $result['file']['labels']);
    }

    /**
     * @dataProvider positions
     */
    #[DataProvider('positions')]
    public function testPositions(string $smile, string $openLabel): void
    {
        $result = (new Converter())->convert([$this->row(['position_category_list' => $smile, 'display_on' => '2'])]);

        self::assertSame($openLabel, $result['file']['labels'][0]['placements'][0]['position']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function positions(): array
    {
        return [
            'top right' => ['top-right', 'tr'],
            'top left' => ['top-left', 'tl'],
            'lower right' => ['lower-right', 'br'],
            'lower left' => ['lower-left', 'bl'],
            'unknown falls back to top left' => ['middle', 'tl'],
        ];
    }

    public function testUnknownPositionIsReported(): void
    {
        $result = (new Converter())->convert([$this->row(['position_category_list' => 'middle', 'display_on' => '2'])]);

        self::assertSame(['Smile label #7 "Eco": unknown position "middle" on listings, using top left.'], $result['warnings']);
    }

    public function testInactiveAllStoresAndOnlyOneArea(): void
    {
        $result = (new Converter())->convert([$this->row(['is_active' => '0', 'store_codes' => [], 'display_on' => '1'])]);
        $label = $result['file']['labels'][0];

        self::assertSame(0, $label['status']);
        self::assertSame([], $label['stores'], 'store 0 = all store views');
        self::assertSame([['area' => 'product', 'position' => 'br', 'sort_order' => 0]], $label['placements']);
    }

    public function testLabelsWithoutImageOrAreaAreSkippedWithAWarning(): void
    {
        $result = (new Converter())->convert([
            $this->row(['image_contents' => null]),
            $this->row(['product_label_id' => '8', 'name' => 'Nowhere', 'display_on' => '']),
            $this->row(['product_label_id' => '9', 'name' => 'Gone attribute', 'attribute_code' => null]),
        ]);

        self::assertSame([], $result['file']['labels']);
        self::assertSame([], $result['file']['designs']);
        self::assertSame([
            'Smile label #7 "Eco": image "eco.png" not found in media/smile_productlabel/imagelabel, skipped.',
            'Smile label #8 "Nowhere": shown nowhere (no listing or product page), skipped.',
            'Smile label #9 "Gone attribute": its attribute no longer exists, skipped.',
        ], $result['warnings']);
    }

    /**
     * @param array<string, mixed> $change
     * @return array<string, mixed>
     */
    private function row(array $change = []): array
    {
        return array_replace([
            'product_label_id' => '7',
            'name' => 'Eco',
            'is_active' => '1',
            'attribute_code' => 'eco_collection',
            'option_id' => '42',
            'image' => 'eco.png',
            'image_contents' => 'PNGDATA',
            'position_category_list' => 'top-left',
            'position_product_view' => 'lower-right',
            'display_on' => '2,1',
            'alt' => 'Eco friendly',
            'store_codes' => ['default'],
        ], $change);
    }
}
