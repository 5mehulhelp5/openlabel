<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Resolver;

use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\DesignFactory;
use Iranimij\OpenLabel\Model\Placement;
use Iranimij\OpenLabel\Model\PlacementFactory;
use Iranimij\OpenLabel\Model\Resolver\Arranger;
use Iranimij\OpenLabel\Model\Resolver\LabelResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class LabelResolverTest extends TestCase
{
    public function testBuildsResolvedLabelsFromTheJoinedRows(): void
    {
        $rows = [
            $this->row(product: 7, label: 2, priority: 1, text: 'Neu', alt: null),
            $this->row(product: 7, label: 1, priority: 0, text: 'Sale', alt: 'Sale badge'),
        ];
        $resolver = $this->resolver($rows);

        $result = $resolver->getForProducts([7, 7, 8], 1, 2);

        self::assertSame([7], array_keys($result));
        self::assertCount(2, $result[7]);
        $first = $result[7][0];
        self::assertSame(1, $first->getLabelId());
        self::assertSame('Label 1', $first->getName());
        self::assertSame(0, $first->getPriority());
        self::assertFalse($first->isHideLowerPriority());
        self::assertTrue($first->isHideOnZeroVariable());
        self::assertSame(7, $first->getProductId());
        self::assertNull($first->getParentProductId());
        self::assertSame('listing', $first->getPlacement()->getArea());
        self::assertSame('tl', $first->getPlacement()->getPosition());
        self::assertSame(1, $first->getPlacement()->getLabelId());
        self::assertSame('Sale', $first->getText());
        self::assertSame('Sale badge', $first->getAltText());
        self::assertSame('#e11d48', $first->getDesign()->getBgColor());
        self::assertSame(2, $result[7][1]->getLabelId());
        self::assertSame('Neu', $result[7][1]->getText());
    }

    public function testEmptyProductListNeedsNoQuery(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        /** @var LabelResolver $resolver */
        $resolver = (new ObjectManager($this))->getObject(LabelResolver::class, ['resource' => $resource, 'arranger' => new Arranger()]);

        self::assertSame([], $resolver->getForProducts([], 1, 0));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function resolver(array $rows): LabelResolver
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'where', 'order', 'distinct', 'limit', 'group'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->willReturn($rows);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $objectManager = new ObjectManager($this);
        $placementFactory = $this->createStub(PlacementFactory::class);
        $placementFactory->method('create')->willReturnCallback(fn () => $objectManager->getObject(Placement::class));
        $designFactory = $this->createStub(DesignFactory::class);
        $designFactory->method('create')->willReturnCallback(fn () => $objectManager->getObject(Design::class));
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-10-09 12:00:00');

        return new LabelResolver($resource, new Arranger(), $placementFactory, $designFactory, $dateTime);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $product, int $label, int $priority, ?string $text, ?string $alt): array
    {
        return [
            'product_id' => $product, 'parent_product_id' => null, 'label_id' => $label, 'name' => 'Label ' . $label,
            'priority' => $priority, 'hide_lower_priority' => 0, 'hide_on_zero_variable' => 1,
            'placement_placement_id' => 10 + $label, 'placement_area' => 'listing', 'placement_position' => 'tl',
            'placement_pin_physical_side' => 0, 'placement_design_id' => null, 'placement_offset_x' => 0, 'placement_offset_y' => 0,
            'placement_max_labels' => 3, 'placement_stacking' => 'vertical', 'placement_gap' => 4, 'placement_sort_order' => 0,
            'design_design_id' => 5, 'design_name' => 'Red', 'design_type' => 'text', 'design_shape' => 'pill', 'design_image_path' => null,
            'design_image_width' => null, 'design_image_height' => null, 'design_bg_color' => '#e11d48', 'design_text_color' => '#fff',
            'design_border_color' => null, 'design_border_width' => 0, 'design_font_size' => 14, 'design_size_mode' => 'percent',
            'design_width' => 18, 'design_height' => null, 'design_opacity' => 100, 'design_rotation' => 0, 'design_custom_css' => null,
            'design_is_system' => 0, 'text' => $text, 'alt_text' => $alt, 'tooltip' => null,
            'area' => 'listing', 'position' => 'tl', 'max_labels' => 3,
        ];
    }
}
