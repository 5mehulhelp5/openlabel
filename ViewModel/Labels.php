<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\ViewModel;

use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\Base\Model\Config\TypedReader;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Template-facing access to resolved labels, memoized per request: the listing calls getForProducts() once with
 * every product id of the page, the item templates then read from memory (02 · Architecture §4, §12).
 */
class Labels implements ArgumentInterface, ResetAfterRequestInterface
{
    public const CONFIG_ENABLED = 'openlabel/general/enabled';
    public const CONFIG_HIDDEN_PAGES = 'openlabel/general/hidden_pages';
    public const CONFIG_HIDDEN_ACTIONS = 'openlabel/general/hidden_actions';

    /** Page types of the "hide labels on these pages" setting → full action names. */
    public const PAGE_TYPES = [
        'home' => ['cms_index_index'],
        'category' => ['catalog_category_view'],
        'search' => ['catalogsearch_result_index', 'catalogsearch_advanced_result'],
        'product' => ['catalog_product_view'],
        'cms' => ['cms_page_view'],
        'cart' => ['checkout_cart_index'],
        'wishlist' => ['wishlist_index_index'],
        'compare' => ['catalog_product_compare_index'],
    ];

    /** @var array<int, ResolvedLabelInterface[]> */
    private array $resolved = [];

    /** @var array<int, ProductInterface> products of the page's collections, by id */
    private array $products = [];

    /** @var array<int, true> remembered ids not resolved yet */
    private array $pending = [];

    /**
     * @param LabelResolverInterface $resolver
     * @param StoreManagerInterface $storeManager
     * @param HttpContext $httpContext
     * @param TypedReader $config
     * @param HttpRequest $request
     */
    public function __construct(
        private readonly LabelResolverInterface $resolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpContext $httpContext,
        private readonly TypedReader $config,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Labels are on for the store and not hidden on this page ("hide labels on these pages", 1.1).
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isModuleEnabled() && !$this->isHiddenPage();
    }

    /**
     * OpenLabel is on for the store, whatever the page (the stylesheet: mini cart labels load on every page).
     *
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->config->getBool(self::CONFIG_ENABLED);
    }

    /**
     * @return bool
     */
    private function isHiddenPage(): bool
    {
        $hidden = [];
        foreach ($this->config->getList(self::CONFIG_HIDDEN_PAGES) as $type) {
            $hidden = array_merge($hidden, self::PAGE_TYPES[$type] ?? []);
        }
        foreach (preg_split('/[\s,]+/', $this->config->getString(self::CONFIG_HIDDEN_ACTIONS)) ?: [] as $action) {
            if ($action !== '') {
                $hidden[] = $action;
            }
        }

        return $hidden !== [] && in_array((string) $this->request->getFullActionName(), $hidden, true);
    }

    /**
     * Resolve the labels of many products with one query; already resolved products cost nothing.
     *
     * @param int[] $productIds
     * @return array<int, ResolvedLabelInterface[]> product id => labels (only products that have labels)
     */
    public function getForProducts(array $productIds): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $wanted = array_values(array_unique(array_merge($productIds, array_keys($this->pending))));
        $this->pending = [];
        $missing = array_values(array_filter($wanted, fn (int $id): bool => !array_key_exists($id, $this->resolved)));
        if ($missing !== []) {
            $fresh = $this->resolver->getForProducts($missing, $this->storeId(), $this->customerGroupId());
            foreach ($missing as $id) {
                $this->resolved[$id] = $fresh[$id] ?? [];
            }
        }
        $result = [];
        foreach ($productIds as $id) {
            if ($this->resolved[$id] !== []) {
                $result[$id] = $this->resolved[$id];
            }
        }

        return $result;
    }

    /**
     * Remember the products of a loaded collection: they are resolved together with the first product a template
     * asks for (one query for the page), and their loaded prices feed the label variables.
     *
     * @param ProductInterface[] $products
     * @return void
     */
    public function remember(array $products): void
    {
        foreach ($products as $product) {
            $id = (int) $product->getId();
            if ($id === 0) {
                continue;
            }
            $this->products[$id] = $product;
            if (!array_key_exists($id, $this->resolved)) {
                $this->pending[$id] = true;
            }
        }
    }

    /**
     * @param int $productId
     * @return ProductInterface|null
     */
    public function getRememberedProduct(int $productId): ?ProductInterface
    {
        return $this->products[$productId] ?? null;
    }

    /**
     * @return array<int, ProductInterface>
     */
    public function getRememberedProducts(): array
    {
        return $this->products;
    }

    /**
     * @param int $productId
     * @return ResolvedLabelInterface[]
     */
    public function getForProduct(int $productId): array
    {
        return $this->getForProducts([$productId])[$productId] ?? [];
    }

    /**
     * Labels of one product in one area, grouped by position in display order.
     *
     * @param int $productId
     * @param string $area listing|product
     * @return array<string, ResolvedLabelInterface[]> position => labels
     */
    public function getStacks(int $productId, string $area): array
    {
        $stacks = [];
        foreach ($this->getForProduct($productId) as $label) {
            if ($label->getPlacement()->getArea() === $area) {
                $stacks[$label->getPlacement()->getPosition()][] = $label;
            }
        }

        return $stacks;
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId();
    }

    /**
     * @return int the visitor's group from the HTTP context (full page cache varies on it already)
     */
    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId();
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @return int
     */
    private function customerGroupId(): int
    {
        return (int) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }

    /**
     * Per-request memory is dropped between requests (application server, integration tests).
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->resolved = [];
        $this->products = [];
        $this->pending = [];
    }
}
