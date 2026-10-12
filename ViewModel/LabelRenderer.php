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

    /**
     * Admin positions → logical classes, mirrored automatically in RTL stores (FE4). Bands outside the image:
     * o + t(op) / b(ottom) / s(tart side) / e(nd side) + the place along that band.
     */
    private const LOGICAL = [
        'tl' => 'ts', 'tc' => 'tc', 'tr' => 'te',
        'ml' => 'ms', 'mc' => 'mc', 'mr' => 'me',
        'bl' => 'bs', 'bc' => 'bc', 'br' => 'be',
        'above_l' => 'ots', 'above_c' => 'otc', 'above_r' => 'ote',
        'below_l' => 'obs', 'below_c' => 'obc', 'below_r' => 'obe',
        'left_t' => 'ost', 'left_m' => 'osm', 'left_b' => 'osb',
        'right_t' => 'oet', 'right_m' => 'oem', 'right_b' => 'oeb',
    ];

    /** Positions that have a side → their physical class for "keep left/right in RTL". */
    private const PHYSICAL = [
        'tl' => 'tl', 'tr' => 'tr', 'ml' => 'ml', 'mr' => 'mr', 'bl' => 'bl', 'br' => 'br',
        'above_l' => 'otl', 'above_r' => 'otr', 'below_l' => 'obl', 'below_r' => 'obr',
        'left_t' => 'olt', 'left_m' => 'olm', 'left_b' => 'olb',
        'right_t' => 'ort', 'right_m' => 'orm', 'right_b' => 'orb',
    ];

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
        return $this->stacksHtml($this->getStacks($product, $area, $loading));
    }

    /**
     * @param array<int, array{classes: string, labels: array<int, array<string, mixed>>}> $stacks
     * @return string
     */
    private function stacksHtml(array $stacks): string
    {
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
     * Labels of one product in the product text (next to the price, next to Add to Cart, under the name), for the
     * theme integration that renders that part of the page.
     *
     * @param ProductInterface $product
     * @param string $area listing|product
     * @param string $position one of PlacementInterface::INLINE_POSITIONS
     * @return string inline stack markup, or an empty string
     */
    public function getInlineHtml(ProductInterface $product, string $area, string $position): string
    {
        return $this->stacksHtml($this->getInlineStacks($product, $area, $position));
    }

    /**
     * @param ProductInterface $product
     * @param string $area
     * @param string $position
     * @return array<int, array{classes: string, labels: array<int, array<string, mixed>>}>
     */
    public function getInlineStacks(ProductInterface $product, string $area, string $position): array
    {
        if (!in_array($position, PlacementInterface::INLINE_POSITIONS, true)) {
            return [];
        }

        return $this->buildStacks($product, $area, [$position], 'lazy');
    }

    /**
     * Stacks on and around the product image (inline positions are rendered by getInlineHtml()).
     *
     * @param ProductInterface $product
     * @param string $area
     * @param string $loading
     * @return array<int, array{classes: string, labels: array<int, array<string, mixed>>}>
     */
    public function getStacks(ProductInterface $product, string $area, string $loading = 'lazy'): array
    {
        return $this->buildStacks($product, $area, PlacementInterface::IMAGE_POSITIONS, $loading);
    }

    /**
     * @param ProductInterface $product
     * @param string $area
     * @param string[] $positions
     * @param string $loading
     * @return array<int, array{classes: string, labels: array<int, array<string, mixed>>}>
     */
    private function buildStacks(ProductInterface $product, string $area, array $positions, string $loading): array
    {
        $this->observeProduct((int) $product->getId());
        $stacks = [];
        foreach ($this->labels->getForProduct((int) $product->getId()) as $label) {
            $placement = $label->getPlacement();
            if ($placement->getArea() === $area && in_array($placement->getPosition(), $positions, true)) {
                $stacks[$placement->getPosition()][] = $label;
            }
        }
        if ($stacks === []) {
            return [];
        }
        $context = $this->context();
        $this->preload($product, $context);
        $debug = $this->config->getBool(self::CONFIG_DEBUG);

        // Product cards are links to the product: labels on their image must not be links or focusable themselves.
        $insideLink = $area === PlacementInterface::AREA_LISTING && array_diff($positions, PlacementInterface::IMAGE_POSITIONS) === [];
        $result = [];
        foreach ($stacks as $labels) {
            $items = [];
            foreach ($labels as $label) {
                $item = $this->labelData($label, $product, $context, $loading, $debug, $insideLink);
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
        if (in_array($position, PlacementInterface::INLINE_POSITIONS, true)) {
            $classes = ['ol-stack', 'ol-stack--h', 'ol-inline', 'ol-inline--' . str_replace('_', '-', $position)];
        } else {
            $positionClass = $placement->isPinPhysicalSide() && isset(self::PHYSICAL[$position])
                ? self::PHYSICAL[$position]
                : (self::LOGICAL[$position] ?? 'ts');
            $classes = [
                'ol-stack',
                $placement->getStacking() === PlacementInterface::STACKING_HORIZONTAL ? 'ol-stack--h' : 'ol-stack--v',
                'ol-pos-' . $positionClass,
            ];
        }
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
     * @param bool $insideLink the label is rendered inside a link (the image of a product card)
     * @return array<string, mixed>|null null when "hide when a variable is zero or empty" applies
     */
    private function labelData(
        ResolvedLabelInterface $label,
        ProductInterface $product,
        Context $context,
        string $loading,
        bool $debug,
        bool $insideLink = false
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

        $classes = $this->designRules->labelClasses($design);
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

        $link = $insideLink ? null : $this->link($label);
        if ($link !== null) {
            $classes[] = 'ol-label--link';
        }
        $tooltip = null;
        if ($label->getTooltip() !== null) {
            $classes[] = 'ol-has-tip';
            $tooltip = [
                // Stable across cached blocks (Hyvä caches product cards): placement + product.
                'id' => 'ol-tip-' . (int) $label->getPlacement()->getPlacementId() . '-' . (int) $product->getId(),
                'html' => $this->renderer->render((string) $label->getTooltip(), $product, $context)->getHtml(),
                'focusable' => !$insideLink && $link === null,
            ];
        }

        return [
            'id' => $label->getLabelId(),
            'classes' => implode(' ', $classes),
            'html' => $html,
            'image' => $image,
            'link' => $link,
            'tooltip' => $tooltip,
            'debug_id' => $debug ? $label->getLabelId() : null,
        ];
    }

    /**
     * @param ResolvedLabelInterface $label
     * @return array{href: string, rel: string|null}|null shop paths become absolute URLs of the current store
     */
    private function link(ResolvedLabelInterface $label): ?array
    {
        $url = $label->getClickUrl();
        if ($url === null) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            $store = $this->storeManager->getStore();
            $base = $store instanceof Store ? (string) $store->getBaseUrl() : '/';
            $url = rtrim($base, '/') . $url;
        }

        return ['href' => $url, 'rel' => $label->isClickNofollow() ? 'nofollow' : null];
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
