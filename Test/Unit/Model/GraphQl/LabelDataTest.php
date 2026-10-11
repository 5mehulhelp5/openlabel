<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\GraphQl;

use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\GraphQl\LabelData;
use Iranimij\OpenLabel\Model\Placement;
use Iranimij\OpenLabel\Model\Resolver\ResolvedLabel;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Rendered;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Magento\Catalog\Model\Product;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LabelDataTest extends TestCase
{
    /** @var string what the stubbed variable renderer returns as HTML */
    private string $renderedHtml = 'Sale -25%';

    public function testTextLabelBecomesStructuredData(): void
    {
        $data = $this->labelData()->toArray(
            $this->label(7, 'text', 'Sale -{SAVE_PERCENT}%', 'tr', 'listing', 'pill'),
            $this->product(),
            new Context(1, 1, 0)
        );

        self::assertSame([
            'id' => 7,
            'name' => 'Label 7',
            'type' => 'text',
            'text' => 'Sale -25%',
            'image_url' => null,
            'alt' => null,
            'position' => 'tr',
            'area' => 'listing',
            'css_class' => 'ol-label ol-label--text ol-d-70 ol-shape-pill',
            'bg_color' => '#e11d48',
            'text_color' => '#ffffff',
            'priority' => 3,
            'design_id' => 70,
        ], $data);
    }

    /**
     * @dataProvider htmlToText
     */
    #[DataProvider('htmlToText')]
    public function testTextIsPlainTextWithoutMarkup(string $html, string $text): void
    {
        $this->renderedHtml = $html;

        $data = $this->labelData()->toArray($this->label(1, 'text', 'x'), $this->product(), new Context(1, 1, 0));

        self::assertSame($text, $data['text']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function htmlToText(): array
    {
        return [
            'allow-listed tags are dropped' => ['<b>Sale</b> <small>-25%</small>', 'Sale -25%'],
            '{BR} becomes a line break' => ['Only 3<br>left', "Only 3\nleft"],
            'self-closing br' => ['Only 3<br />left', "Only 3\nleft"],
            'entities are decoded' => ['Tom &amp; Jerry &quot;new&quot;', 'Tom & Jerry "new"'],
            'surrounding space is trimmed' => ['  New  ', 'New'],
        ];
    }

    public function testImageLabelHasUrlAndAltButNoText(): void
    {
        $data = $this->labelData()->toArray(
            $this->label(2, 'image', null, 'bl', 'product'),
            $this->product(),
            new Context(1, 1, 0)
        );

        self::assertNull($data['text']);
        self::assertSame('https://shop.test/media/openlabel/designs/new.svg', $data['image_url']);
        self::assertSame('New in', $data['alt']);
        self::assertSame('ol-label ol-label--image ol-d-20', $data['css_class']);
        self::assertSame('product', $data['area']);
        self::assertSame('bl', $data['position']);
    }

    public function testEmptyColoursAreNull(): void
    {
        $label = $this->label(3, 'shape', 'Hot', 'tl', 'listing', 'circle');
        $label->getDesign()->setBgColor('')->setTextColor(null);

        $data = $this->labelData()->toArray($label, $this->product(), new Context(1, 1, 0));

        self::assertNull($data['bg_color']);
        self::assertNull($data['text_color']);
        self::assertSame('ol-label ol-label--shape ol-d-30 ol-shape-circle', $data['css_class']);
    }

    private function labelData(): LabelData
    {
        $renderer = $this->createStub(Renderer::class);
        $renderer->method('render')->willReturnCallback(fn (): Rendered => new Rendered($this->renderedHtml, []));
        $imageUrl = $this->createStub(ImageUrl::class);
        $imageUrl->method('get')->willReturnCallback(
            static fn (?string $path): ?string => $path === null ? null : 'https://shop.test/media/openlabel/' . $path
        );

        return new LabelData($renderer, new DesignRules(), $imageUrl);
    }

    private function product(): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(100);

        return $product;
    }

    private function label(
        int $id,
        string $type,
        ?string $text,
        string $position = 'tl',
        string $area = 'listing',
        ?string $shape = null
    ): ResolvedLabel {
        $om = new ObjectManager($this);
        /** @var Placement $placement */
        $placement = $om->getObject(Placement::class);
        $placement->setData(['placement_id' => $id + 10, 'area' => $area, 'position' => $position]);
        /** @var Design $design */
        $design = $om->getObject(Design::class);
        $design->setData([
            'design_id' => $id * 10, 'type' => $type, 'shape' => $shape,
            'image_path' => $type === 'image' ? 'designs/new.svg' : null,
            'bg_color' => '#e11d48', 'text_color' => '#ffffff',
        ]);
        $design->setText($text)->setAltText($type === 'image' ? 'New in' : null);

        return new ResolvedLabel($id, 'Label ' . $id, 3, false, 100, null, $placement, $design);
    }
}
