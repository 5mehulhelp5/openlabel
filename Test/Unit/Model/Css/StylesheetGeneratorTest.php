<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Css;

use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Iranimij\OpenLabel\Model\Css\StylesheetGenerator;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Placement;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class StylesheetGeneratorTest extends TestCase
{
    private const STRUCTURAL = ".ol-anchor{position:relative}\n.ol-stack{position:absolute}";

    public function testStructuralCssComesFirstThenDesignsThenPlacementsThenCustomCss(): void
    {
        $css = $this->generator()->compose(
            self::STRUCTURAL,
            [
                $this->design(2, ['bg_color' => '#111111', 'custom_css' => '.ol-d-2{letter-spacing:.1em}']),
                $this->design(1, ['bg_color' => '#222222']),
            ],
            [$this->placement(9, 6)]
        );

        $structural = strpos($css, '.ol-stack{position:absolute}');
        $design1 = strpos($css, '.ol-d-1{');
        $design2 = strpos($css, '.ol-d-2{--ol-bg');
        $placement = strpos($css, '.ol-p-9{');
        $custom = strpos($css, 'letter-spacing');
        self::assertNotFalse($structural);
        self::assertTrue($structural < $design1 && $design1 < $design2 && $design2 < $placement && $placement < $custom);
    }

    public function testCustomCssIsSanitized(): void
    {
        $css = $this->generator()->compose(
            self::STRUCTURAL,
            [$this->design(3, ['custom_css' => '@import url(https://evil.test/x.css);.ol-d-3{color:red}'])],
            []
        );

        self::assertStringNotContainsString('@import', $css);
        self::assertStringContainsString('color:red', $css);
    }

    public function testGlobalCssComesLastAndIsSanitized(): void
    {
        $css = $this->generator()->compose(
            self::STRUCTURAL,
            [$this->design(3, ['custom_css' => '.ol-d-3{color:red}'])],
            [],
            '.ol-label{letter-spacing:.05em}@import url(https://evil.test/x.css);.x{width:expression(alert(1))}'
        );

        self::assertStringContainsString("/* global */\n.ol-label{letter-spacing:.05em}", $css);
        self::assertGreaterThan(strpos($css, 'color:red'), strpos($css, 'letter-spacing'));
        self::assertStringNotContainsString('@import', $css);
        self::assertStringNotContainsString('expression', $css);
    }

    public function testEmptyGlobalCssAddsNothing(): void
    {
        self::assertStringNotContainsString('global', $this->generator()->compose(self::STRUCTURAL, [], [], "  \n"));
    }

    public function testCommentHeaderOfTheStructuralSourceIsKeptOutOfTheOutput(): void
    {
        $css = $this->generator()->compose("/**\n * Copyright\n */\n.ol-anchor{position:relative}", [], []);

        self::assertStringNotContainsString('Copyright', $css);
        self::assertStringStartsWith('/* OpenLabel', $css);
    }

    public function testOutputIsDeterministic(): void
    {
        $designs = [$this->design(1, ['bg_color' => '#123456'])];

        self::assertSame(
            $this->generator()->compose(self::STRUCTURAL, $designs, []),
            $this->generator()->compose(self::STRUCTURAL, $designs, [])
        );
    }

    public function testHundredDesignsStayUnderTwentyKilobytes(): void
    {
        $structural = (string) file_get_contents(__DIR__ . '/../../../../view/base/web/css/openlabel.css');
        $designs = [];
        $placements = [];
        for ($id = 1; $id <= 100; $id++) {
            $designs[] = $this->design($id, [
                'bg_color' => '#e11d48', 'text_color' => '#ffffff', 'border_color' => '#000000', 'border_width' => 1,
                'opacity' => 95, 'rotation' => -12, 'width' => 18, 'font_size' => 16,
            ]);
            $placements[] = $this->placement($id, 8);
        }

        $css = $this->generator()->compose($structural, $designs, $placements);

        self::assertLessThanOrEqual(20 * 1024, strlen($css), 'Architecture §10: ≤ 20 KB for 100 designs');
        self::assertLessThanOrEqual(8 * 1024, strlen((string) gzencode($css, 9)), 'FE10: ≤ 8 KB gzipped');
    }

    private function generator(): StylesheetGenerator
    {
        /** @var StylesheetGenerator $generator */
        $generator = (new ObjectManager($this))->getObject(StylesheetGenerator::class, [
            'designRules' => new DesignRules(),
            'sanitizer' => new Sanitizer(),
        ]);

        return $generator;
    }

    /**
     * @param int $id
     * @param array<string, mixed> $data
     * @return Design
     */
    private function design(int $id, array $data): Design
    {
        /** @var Design $design */
        $design = (new ObjectManager($this))->getObject(Design::class);
        $design->setData(array_merge(['design_id' => $id, 'type' => 'text'], $data));

        return $design;
    }

    private function placement(int $id, int $offsetX): Placement
    {
        /** @var Placement $placement */
        $placement = (new ObjectManager($this))->getObject(Placement::class);
        $placement->setData(['placement_id' => $id, 'offset_x' => $offsetX]);

        return $placement;
    }
}
