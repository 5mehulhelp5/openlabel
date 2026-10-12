<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\ViewModel;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\Placement;
use Iranimij\OpenLabel\Model\Resolver\ResolvedLabel;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Rendered;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Iranimij\OpenLabel\ViewModel\LabelRenderer;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Catalog\Model\Product;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LabelRendererTest extends TestCase
{
    /** @var ResolvedLabel[] */
    private array $resolved = [];

    private bool $debug = false;

    /** @var string[] texts handed to preloadFor() */
    private array $preloadedTexts = [];

    public function testOneStackPerPositionWithLogicalPositionClasses(): void
    {
        $this->resolved = [
            $this->label(1, 'tl', 'vertical', 'text', 'Sale'),
            $this->label(2, 'tl', 'vertical', 'text', 'New'),
            $this->label(3, 'br', 'horizontal', 'shape', 'Hot', 'pill'),
        ];

        $stacks = $this->renderer()->getStacks($this->product(), 'listing');

        self::assertCount(2, $stacks);
        self::assertSame('ol-stack ol-stack--v ol-pos-ts', $stacks[0]['classes']);
        self::assertSame([1, 2], array_column($stacks[0]['labels'], 'id'));
        self::assertSame('ol-stack ol-stack--h ol-pos-be', $stacks[1]['classes']);
        self::assertSame('ol-label ol-label--shape ol-d-30 ol-shape-pill', $stacks[1]['labels'][0]['classes']);
        self::assertSame('<b>Hot</b>', $stacks[1]['labels'][0]['html']);
    }

    /**
     * @dataProvider positions
     */
    #[DataProvider('positions')]
    public function testPositionMapping(string $position, bool $pinned, string $class): void
    {
        $this->resolved = [$this->label(1, $position, 'vertical', 'text', 'X', null, $pinned)];

        $stacks = $this->renderer()->getStacks($this->product(), 'listing');

        self::assertStringEndsWith($class, $stacks[0]['classes']);
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function positions(): array
    {
        return [
            'top left' => ['tl', false, 'ol-pos-ts'],
            'top centre' => ['tc', false, 'ol-pos-tc'],
            'top right' => ['tr', false, 'ol-pos-te'],
            'middle left' => ['ml', false, 'ol-pos-ms'],
            'centre' => ['mc', false, 'ol-pos-mc'],
            'middle right' => ['mr', false, 'ol-pos-me'],
            'bottom left' => ['bl', false, 'ol-pos-bs'],
            'bottom centre' => ['bc', false, 'ol-pos-bc'],
            'bottom right' => ['br', false, 'ol-pos-be'],
            'pinned left stays left in RTL' => ['tl', true, 'ol-pos-tl'],
            'pinned right' => ['br', true, 'ol-pos-br'],
            'pinning a centre has no effect' => ['tc', true, 'ol-pos-tc'],
        ];
    }

    public function testPlacementWithOffsetsGetsItsCustomPropertyClass(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', 'X', null, false, 6)];

        self::assertSame('ol-stack ol-stack--v ol-pos-ts ol-p-11', $this->renderer()->getStacks($this->product(), 'listing')[0]['classes']);
    }

    public function testOtherAreasAreIgnored(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', 'X')];

        self::assertSame([], $this->renderer()->getStacks($this->product(), 'product'));
        self::assertSame('', $this->renderer()->getStacksHtml($this->product(), 'product'));
    }

    public function testImageLabelCarriesDimensionsAltAndTheProductImageLoading(): void
    {
        $this->resolved = [$this->label(1, 'tr', 'vertical', 'image', null)];

        $label = $this->renderer()->getStacks($this->product(), 'listing', 'eager')[0]['labels'][0];

        self::assertSame('ol-label ol-label--image ol-d-10', $label['classes']);
        self::assertSame([
            'src' => 'https://shop.test/media/openlabel/designs/new.svg',
            'width' => 80,
            'height' => 40,
            'alt' => 'New in',
            'loading' => 'eager',
        ], $label['image']);
    }

    public function testDebugModeExposesTheLabelId(): void
    {
        $this->resolved = [$this->label(7, 'tl', 'vertical', 'text', 'X')];
        self::assertNull($this->renderer()->getStacks($this->product(), 'listing')[0]['labels'][0]['debug_id']);

        $this->debug = true;
        self::assertSame(7, $this->renderer()->getStacks($this->product(), 'listing')[0]['labels'][0]['debug_id']);
    }

    public function testIdentitiesNameEveryRenderedLabelAndDesign(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', 'X'), $this->label(4, 'tr', 'vertical', 'text', 'Y')];
        $renderer = $this->renderer();

        $renderer->getStacks($this->product(), 'listing');

        self::assertSame(
            ['cat_p_100', 'openlabel_1', 'openlabel_design_10', 'openlabel_4', 'openlabel_design_40'],
            $renderer->getIdentities()
        );
    }

    public function testIdentitiesCanBeCollectedWithoutRendering(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', 'X'), $this->label(2, 'tl', 'vertical', 'text', 'Y')];
        $renderer = $this->renderer();

        $tags = $renderer->collectIdentities(100);

        self::assertSame(['openlabel_1', 'openlabel_design_10', 'openlabel_2', 'openlabel_design_20'], $tags);
        self::assertSame(array_merge(['cat_p_100'], $tags), $renderer->getIdentities(), 'the page is tagged too');
    }

    public function testProductsWithoutLabelsStillTagThePage(): void
    {
        $renderer = $this->renderer();

        $renderer->observeProduct(55);
        $renderer->getStacks($this->product(), 'listing');

        self::assertSame(['cat_p_55', 'cat_p_100'], $renderer->getIdentities());
    }

    public function testVariablesArePreloadedOnceForAllRememberedProducts(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', '-{SAVE_PERCENT}%')];
        $renderer = $this->renderer();

        $renderer->getStacks($this->product(), 'listing');
        $renderer->getStacks($this->product(), 'listing');

        self::assertSame(['-{SAVE_PERCENT}%'], $this->preloadedTexts);
    }

    public function testHideOnZeroDropsALabelWhoseVariableIsZeroOrEmpty(): void
    {
        $this->resolved = [
            $this->label(1, 'tl', 'vertical', 'text', 'Only {ZERO} left', hideOnZero: true),
            $this->label(2, 'tl', 'vertical', 'text', 'Only {ZERO} left'),
            $this->label(3, 'tl', 'vertical', 'text', 'Sale', hideOnZero: true),
        ];

        $stacks = $this->renderer()->getStacks($this->product(), 'listing');

        self::assertSame([2, 3], array_column($stacks[0]['labels'], 'id'), 'the flag drops only its own label, and only when a variable is empty');
    }

    public function testAStackWhoseLabelsAreAllHiddenIsNotRendered(): void
    {
        $this->resolved = [
            $this->label(1, 'tl', 'vertical', 'text', '{ZERO} sold', hideOnZero: true),
            $this->label(2, 'br', 'vertical', 'text', 'New'),
        ];

        $stacks = $this->renderer()->getStacks($this->product(), 'listing');

        self::assertCount(1, $stacks);
        self::assertSame([2], array_column($stacks[0]['labels'], 'id'));
        self::assertSame('', $this->renderer()->getStacksHtml($this->product(), 'product'), 'no labels, no markup');
    }

    public function testHiddenLabelsDoNotTagThePage(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', '{ZERO} sold', hideOnZero: true)];
        $renderer = $this->renderer();

        self::assertSame('', $renderer->getStacksHtml($this->product(), 'listing'));
        self::assertSame(['cat_p_100'], $renderer->getIdentities(), 'the product tag stays: the label shows once the variable is not zero');
    }

    public function testStacksHtmlRendersTheTemplateWithThePreparedStacks(): void
    {
        $this->resolved = [$this->label(1, 'tl', 'vertical', 'text', 'X')];

        self::assertSame('stacks:1', $this->renderer()->getStacksHtml($this->product(), 'listing'));
    }

    private function renderer(): LabelRenderer
    {
        $labels = $this->createStub(Labels::class);
        $labels->method('getForProduct')->willReturnCallback(fn (): array => $this->resolved);
        $labels->method('getForProducts')->willReturnCallback(fn (): array => [100 => $this->resolved]);
        $labels->method('getStoreId')->willReturn(1);
        $labels->method('getCustomerGroupId')->willReturn(0);
        $labels->method('getRememberedProducts')->willReturn([]);

        $renderer = $this->createStub(Renderer::class);
        $renderer->method('render')->willReturnCallback(
            static fn (string $text): Rendered => new Rendered('<b>' . $text . '</b>', str_contains($text, '{ZERO}') ? ['ZERO'] : [])
        );
        $renderer->method('preloadFor')->willReturnCallback(function (array $products, Context $context, array $texts): void {
            $this->preloadedTexts = array_merge($this->preloadedTexts, $texts);
        });

        $imageUrl = $this->createStub(ImageUrl::class);
        $imageUrl->method('get')->willReturnCallback(
            static fn (?string $path): string => 'https://shop.test/media/openlabel/' . $path
        );
        $config = $this->createStub(TypedReader::class);
        $config->method('getBool')->willReturnCallback(fn (): bool => $this->debug);

        $block = $this->createStub(Template::class);
        $block->method('setTemplate')->willReturnSelf();
        $block->method('setData')->willReturnCallback(function (string $key, mixed $value) use (&$block): Template {
            $this->blockData[$key] = $value;

            return $block;
        });
        $block->method('toHtml')->willReturnCallback(fn (): string => 'stacks:' . count($this->blockData['stacks'] ?? []));
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($block);

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new LabelRenderer($labels, $renderer, new DesignRules(), $imageUrl, $config, $layout, $storeManager);
    }

    /** @var array<string, mixed> */
    private array $blockData = [];

    private function product(): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(100);

        return $product;
    }

    private function label(
        int $id,
        string $position,
        string $stacking,
        string $type,
        ?string $text,
        ?string $shape = null,
        bool $pinned = false,
        int $offsetX = 0,
        bool $hideOnZero = false
    ): ResolvedLabel {
        $om = new ObjectManager($this);
        /** @var Placement $placement */
        $placement = $om->getObject(Placement::class);
        $placement->setData([
            'placement_id' => $id + 10, 'area' => 'listing', 'position' => $position, 'stacking' => $stacking,
            'pin_physical_side' => $pinned, 'offset_x' => $offsetX,
        ]);
        /** @var Design $design */
        $design = $om->getObject(Design::class);
        $design->setData([
            'design_id' => $id * 10, 'type' => $type, 'shape' => $shape, 'image_path' => 'designs/new.svg',
            'image_width' => 80, 'image_height' => 40,
        ]);
        $design->setText($text)->setAltText($type === 'image' ? 'New in' : null);

        return new ResolvedLabel($id, 'Label ' . $id, $id, false, 100, null, $placement, $design, $hideOnZero);
    }
}
