<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Css;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Placement;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class DesignRulesTest extends TestCase
{
    public function testTextDesignBecomesOneCustomPropertyLine(): void
    {
        $design = $this->design(12, [
            'bg_color' => '#E11D48', 'text_color' => '#ffffff', 'border_color' => '#000', 'border_width' => 2,
            'opacity' => 90, 'rotation' => -12, 'size_mode' => 'percent', 'width' => 18, 'font_size' => 15,
        ]);

        self::assertSame(
            '.ol-d-12{--ol-bg:#e11d48;--ol-fg:#ffffff;--ol-border:#000;--ol-bw:2px;--ol-op:0.9;--ol-rot:-12deg;'
            . '--ol-w:18cqw;--ol-fs-max:15px}',
            (new DesignRules())->forDesign($design)
        );
    }

    public function testPixelSizeAndDefaultsAreOmittedWhenUnset(): void
    {
        $design = $this->design(3, ['size_mode' => 'px', 'width' => 80, 'opacity' => 100, 'font_size' => 14]);

        self::assertSame('.ol-d-3{--ol-w:80px;--ol-fs-max:14px}', (new DesignRules())->forDesign($design));
    }

    public function testInvalidColoursNeverReachTheStylesheet(): void
    {
        $design = $this->design(4, ['bg_color' => 'red;}body{display:none', 'text_color' => 'url(x)']);

        $css = (new DesignRules())->forDesign($design);

        self::assertStringNotContainsString('display', $css);
        self::assertStringNotContainsString('url', $css);
        self::assertStringNotContainsString('--ol-bg', $css);
    }

    public function testBreakpointSizesBecomeMediaRules(): void
    {
        $design = $this->design(7, ['size_mode' => 'percent', 'width' => 18, 'font_size' => 15]);
        $design->setSizes(['sm' => ['width' => 26, 'font_size' => 11], 'md' => ['width' => 22], 'lg' => ['font_size' => 16]]);

        self::assertSame(
            '.ol-d-7{--ol-w:18cqw;--ol-fs-max:15px}'
            . "\n@media (max-width:639px){.ol-d-7{--ol-w:26cqw;--ol-fs-max:11px}}"
            . "\n@media (min-width:640px) and (max-width:1023px){.ol-d-7{--ol-w:22cqw}}"
            . "\n@media (min-width:1024px){.ol-d-7{--ol-fs-max:16px}}",
            (new DesignRules())->forDesign($design)
        );
    }

    public function testBreakpointWidthsUseTheDesignsSizeUnit(): void
    {
        $design = $this->design(8, ['size_mode' => 'px', 'width' => 80]);
        $design->setSizes(['sm' => ['width' => 60]]);

        self::assertStringEndsWith('@media (max-width:639px){.ol-d-8{--ol-w:60px}}', (new DesignRules())->forDesign($design));
    }

    public function testLabelClassesNameTypeDesignShapeAnimationAndHover(): void
    {
        $rules = new DesignRules();

        self::assertSame(
            ['ol-label', 'ol-label--shape', 'ol-d-9', 'ol-shape-pill'],
            $rules->labelClasses($this->design(9, ['type' => 'shape', 'shape' => 'pill']))
        );
        self::assertSame(
            ['ol-label', 'ol-label--text', 'ol-d-9', 'ol-anim-pop', 'ol-hover-lift'],
            $rules->labelClasses($this->design(9, ['type' => 'text', 'animation' => 'pop', 'hover_effect' => 'lift']))
        );
        self::assertSame(
            ['ol-label', 'ol-label--image', 'ol-d-9', 'ol-anim-fade'],
            $rules->labelClasses($this->design(9, ['type' => 'image', 'shape' => 'pill', 'animation' => 'fade', 'hover_effect' => 'shake'])),
            'images have no shape; unknown effects are ignored'
        );
    }

    public function testDesignWithoutIdProducesNothing(): void
    {
        self::assertSame('', (new DesignRules())->forDesign($this->design(null, [])));
    }

    public function testPlacementWithDefaultsNeedsNoRule(): void
    {
        self::assertNull((new DesignRules())->forPlacement($this->placement(5, 0, 0, 4)));
    }

    public function testPlacementOffsetsAndGap(): void
    {
        self::assertSame(
            '.ol-p-5{--ol-gap:8px;--ol-ox:-6px;--ol-oy:10px}',
            (new DesignRules())->forPlacement($this->placement(5, -6, 10, 8))
        );
    }

    public function testPlacementClassName(): void
    {
        self::assertSame('ol-p-7', (new DesignRules())->placementClass(7));
        self::assertSame('ol-d-9', (new DesignRules())->designClass(9));
    }

    /**
     * @param int|null $id
     * @param array<string, mixed> $data
     * @return DesignInterface
     */
    private function design(?int $id, array $data): DesignInterface
    {
        /** @var Design $design */
        $design = (new ObjectManager($this))->getObject(Design::class);
        $design->setData(array_merge(['design_id' => $id, 'type' => 'text', 'width' => 0, 'font_size' => 0], $data));

        return $design;
    }

    private function placement(int $id, int $x, int $y, int $gap): PlacementInterface
    {
        /** @var Placement $placement */
        $placement = (new ObjectManager($this))->getObject(Placement::class);
        $placement->setData(['placement_id' => $id, 'offset_x' => $x, 'offset_y' => $y, 'gap' => $gap]);

        return $placement;
    }
}
