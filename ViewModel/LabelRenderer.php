<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\ViewModel;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;
use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\Label;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Turns the resolved labels of one product and area into stacks of label markup (the `ol-*` CSS contract,
 * 10 · Front-end Review FE7) and remembers which labels and designs were rendered for the page cache tags.
 * Theme integrations call getStacksHtml() inside a positioned image wrapper.
 */
class LabelRenderer implements ArgumentInterface, ResetAfterRequestInterface
{
    public const TEMPLATE = 'Iranimij_OpenLabel::base/stacks.phtml';
    public const CONFIG_DEBUG = 'openlabel/general/debug';

    /** Admin positions → logical classes, mirrored automatically in RTL stores (FE4). */
    private const LOGICAL = [
        'tl' => 'ts', 'tc' => 'tc', 'tr' => 'te',
        'ml' => 'ms', 'mc' => 'mc', 'mr' => 'me',
        'bl' => 'bs', 'bc' => 'bc', 'br' => 'be',
    ];

    /** Positions that have a side, so "keep left/right in RTL" applies to them. */
    private const SIDED = ['tl', 'tr', 'ml', 'mr', 'bl', 'br'];

    /** @var array<string, true> */
    private array $identities = [];

    /** @var array<int, true> products whose variables are loaded */
    private array $preloaded = [];

    private ?Template $block = null;

    /**
     * @param Labels $labels
     * @param Renderer $renderer
     * @param DesignRules $designRules
     * @param ImageUrl $imageUrl
     * @param TypedReader $config
     * @param LayoutInterface $layout
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Labels $labels,
        private readonly Renderer $renderer,
        private readonly DesignRules $designRules,
        private readonly ImageUrl $imageUrl,
        private readonly TypedReader $config,
        private readonly LayoutInterface $layout,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param ProductInterface $product
     * @param string $area listing|product
     * @param string $loading the product image's loading attribute, reused by image labels (FE6)
     * @return string stacks markup, or an empty string when the product has no label in this area
     */
    public function getStacksHtml(ProductInterface $product, string $area, string $loading = 'lazy'): string
    {
        $stacks = $this->getStacks($product, $area, $loading);
        if ($stacks === []) {
            return '';
        }
        if ($this->block === null) {
            /** @var Template $block */
            $block = $this->layout->createBlock(Template::class, '', ['data' => ['template' => self::TEMPLATE]]);
            $this->block = $block;
        }

        return (string) $this->block->setData('stacks', $stacks)->toHtml();
    }

    /**
     * @param ProductInterface $product
     * @param string $area
     * @param string $loading
     * @return array<int, array{classes: string, labels: array<int, array<string, mixed>>}>
     */
    public function getStacks(ProductInterface $product, string $area, string $loading = 'lazy'): array
    {
        $this->observeProduct((int) $product->getId());
        $stacks = [];
        foreach ($this->labels->getForProduct((int) $product->getId()) as $label) {
            if ($label->getPlacement()->getArea() === $area) {
                $stacks[$label->getPlacement()->getPosition()][] = $label;
            }
        }
        if ($stacks === []) {
            return [];
        }
        $context = $this->context();
        $this->preload($product, $context);
        $debug = $this->config->getBool(self::CONFIG_DEBUG);

        $result = [];
        foreach ($stacks as $labels) {
            $items = [];
            foreach ($labels as $label) {
                $item = $this->labelData($label, $product, $context, $loading, $debug);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            if ($items !== []) {
                $result[] = ['classes' => $this->stackClasses($labels[0]->getPlacement()), 'labels' => $items];
            }
        }

        return $result;
    }

    /**
     * @return string[] `openlabel_<label_id>`, `openlabel_design_<design_id>` of every label rendered so far and
     *                  `cat_p_<id>` of every product looked at
     */
    public function getIdentities(): array
    {
        return array_keys($this->identities);
    }

    /**
     * Tags the page with the product (`cat_p_<id>`): a label that starts matching it later purges this page even
     * where the theme does not tag the page with its products (core product widgets on CMS pages).
     *
     * @param int $productId
     * @return void
     */
    public function observeProduct(int $productId): void
    {
        if ($productId > 0) {
            $this->identities[Product::CACHE_TAG . '_' . $productId] = true;
        }
    }

    /**
     * Cache tags of a product's labels without rendering them: theme block caches that skip rendering on a cache
     * hit still tag the page (and their own cache entry) correctly.
     *
     * @param int $productId
     * @return string[]
     */
    public function collectIdentities(int $productId): array
    {
        $this->observeProduct($productId);
        $tags = [];
        foreach ($this->labels->getForProduct($productId) as $label) {
            $tags[] = Label::CACHE_TAG_PREFIX . $label->getLabelId();
            $tags[] = Design::CACHE_TAG . '_' . (int) $label->getDesign()->getDesignId();
        }
        $tags = array_values(array_unique($tags));
        foreach ($tags as $tag) {
            $this->identities[$tag] = true;
        }

        return $tags;
    }

    /**
     * @param PlacementInterface $placement
     * @return string
     */
    private function stackClasses(PlacementInterface $placement): string
    {
        $position = $placement->getPosition();
        $positionClass = $placement->isPinPhysicalSide() && in_array($position, self::SIDED, true)
            ? $position
            : (self::LOGICAL[$position] ?? 'ts');
        $classes = [
            'ol-stack',
            $placement->getStacking() === PlacementInterface::STACKING_HORIZONTAL ? 'ol-stack--h' : 'ol-stack--v',
            'ol-pos-' . $positionClass,
        ];
        if ($this->designRules->forPlacement($placement) !== null) {
            $classes[] = $this->designRules->placementClass((int) $placement->getPlacementId());
        }

        return implode(' ', $classes);
    }

    /**
     * @param ResolvedLabelInterface $label
     * @param ProductInterface $product
     * @param Context $context
     * @param string $loading
     * @param bool $debug
     * @return array<string, mixed>|null null when "hide when a variable is zero or empty" applies
     */
    private function labelData(
        ResolvedLabelInterface $label,
        ProductInterface $product,
        Context $context,
        string $loading,
        bool $debug
    ): ?array {
        $design = $label->getDesign();
        $designId = (int) $design->getDesignId();
        $html = '';
        if ($design->getType() !== DesignInterface::TYPE_IMAGE) {
            $rendered = $this->renderer->render((string) $label->getText(), $product, $context);
            if ($label->isHideOnZeroVariable() && $rendered->hasEmptyVariable()) {
                return null;
            }
            $html = $rendered->getHtml();
        }
        $this->identities[Label::CACHE_TAG_PREFIX . $label->getLabelId()] = true;
        $this->identities[Design::CACHE_TAG . '_' . $designId] = true;

        $classes = ['ol-label', 'ol-label--' . $design->getType(), $this->designRules->designClass($designId)];
        if ($design->getType() !== DesignInterface::TYPE_IMAGE && (string) $design->getShape() !== '') {
            $classes[] = 'ol-shape-' . $design->getShape();
        }
        $image = null;
        if ($design->getType() === DesignInterface::TYPE_IMAGE) {
            $image = [
                'src' => (string) $this->imageUrl->get($design->getImagePath()),
                'width' => $design->getImageWidth(),
                'height' => $design->getImageHeight(),
                'alt' => (string) $label->getAltText(),
                'loading' => $loading === 'eager' ? 'eager' : 'lazy',
            ];
        }

        return [
            'id' => $label->getLabelId(),
            'classes' => implode(' ', $classes),
            'html' => $html,
            'image' => $image,
            'debug_id' => $debug ? $label->getLabelId() : null,
        ];
    }

    /**
     * Load review and sales data for all products remembered so far in one go, only for the variables the
     * page's labels use.
     *
     * @param ProductInterface $product
     * @param Context $context
     * @return void
     */
    private function preload(ProductInterface $product, Context $context): void
    {
        if (isset($this->preloaded[(int) $product->getId()])) {
            return;
        }
        $products = $this->labels->getRememberedProducts();
        $products[(int) $product->getId()] = $product;
        $products = array_diff_key($products, $this->preloaded);
        $texts = [];
        foreach ($this->labels->getForProducts(array_keys($products)) as $labels) {
            foreach ($labels as $label) {
                $text = (string) $label->getText();
                if (str_contains($text, '{')) {
                    $texts[$text] = $text;
                }
            }
        }
        if ($texts !== []) {
            $this->renderer->preloadFor(array_values($products), $context, array_values($texts));
        }
        $this->preloaded += array_fill_keys(array_keys($products), true);
    }

    /**
     * @return Context
     */
    private function context(): Context
    {
        $store = $this->storeManager->getStore();

        return new Context(
            $this->labels->getStoreId(),
            $store instanceof Store ? (int) $store->getWebsiteId() : 0,
            $this->labels->getCustomerGroupId()
        );
    }

    /**
     * Per-request memory is dropped between requests (application server, integration tests).
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->identities = [];
        $this->preloaded = [];
        $this->block = null;
    }
}
