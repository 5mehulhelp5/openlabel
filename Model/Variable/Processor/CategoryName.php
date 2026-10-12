<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Variable\Processor;

use Iranimij\OpenLabel\Api\VariablePreloadInterface;
use Iranimij\OpenLabel\Api\VariableProcessorInterface;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * {CATEGORY_NAME}: on a category page the category being browsed; everywhere else (product page, search, widgets,
 * GraphQL) the product's deepest active category in the store's tree, so the page cache never depends on where the
 * shopper came from. Loaded for a whole listing with one query plus the category names.
 */
class CategoryName implements VariableProcessorInterface, VariablePreloadInterface
{
    /** @var array<string, string|null> "store/product" => category name */
    private array $names = [];

    /**
     * @param Registry $registry
     * @param ResourceConnection $resource
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly ResourceConnection $resource,
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'CATEGORY_NAME';
    }

    /**
     * @inheritDoc
     */
    public function preload(array $products, Context $context): void
    {
        $ids = [];
        foreach ($products as $product) {
            $id = (int) $product->getId();
            if ($id > 0 && !array_key_exists($this->key($context, $id), $this->names)) {
                $ids[] = $id;
            }
        }
        if ($ids !== []) {
            $this->load($ids, $context);
        }
    }

    /**
     * @inheritDoc
     */
    public function getValue(ProductInterface $product, Context $context, string $argument = ''): Value
    {
        $browsed = $this->browsedCategory();
        if ($browsed !== null) {
            return Value::text($browsed);
        }
        $id = (int) $product->getId();
        if ($id === 0) {
            return Value::empty();
        }
        if (!array_key_exists($this->key($context, $id), $this->names)) {
            $this->load([$id], $context);
        }

        return Value::text($this->names[$this->key($context, $id)]);
    }

    /**
     * The category page's category; null on product pages, where Magento may register the category the shopper
     * came from (a cached page must not depend on that).
     *
     * @return string|null
     */
    private function browsedCategory(): ?string
    {
        $category = $this->registry->registry('current_category');
        if (!$category instanceof Category || $this->registry->registry('current_product') !== null) {
            return null;
        }
        $name = (string) $category->getName();

        return $name === '' ? null : $name;
    }

    /**
     * @param int[] $ids
     * @param Context $context
     * @return void
     */
    private function load(array $ids, Context $context): void
    {
        foreach ($ids as $id) {
            $this->names[$this->key($context, $id)] = null;
        }
        $store = $this->storeManager->getStore($context->storeId);
        $rootId = $store instanceof Store ? (int) $store->getRootCategoryId() : 0;
        if ($rootId === 0) {
            return;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['cp' => $this->resource->getTableName('catalog_category_product')], ['product_id', 'category_id'])
            ->join(['c' => $this->resource->getTableName('catalog_category_entity')], 'c.entity_id = cp.category_id', [])
            ->where('cp.product_id IN (?)', $ids, \Zend_Db::INT_TYPE)
            ->where('c.path LIKE ?', '1/' . $rootId . '/%')
            ->order(['cp.product_id', 'c.level DESC', 'cp.position', 'c.entity_id']);
        $rows = $connection->fetchAll($select);
        if ($rows === []) {
            return;
        }
        $names = $this->activeNames(array_values(array_unique(array_map('intval', array_column($rows, 'category_id')))), $context);
        foreach ($rows as $row) {
            $key = $this->key($context, (int) $row['product_id']);
            if ($this->names[$key] === null && isset($names[(int) $row['category_id']])) {
                $this->names[$key] = $names[(int) $row['category_id']];
            }
        }
    }

    /**
     * @param int[] $categoryIds
     * @param Context $context
     * @return array<int, string> active categories' store-view names
     */
    private function activeNames(array $categoryIds, Context $context): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($context->storeId)
            ->addAttributeToSelect('name')
            ->addIsActiveFilter()
            ->addIdFilter($categoryIds);
        $names = [];
        foreach ($collection as $category) {
            $name = (string) $category->getName();
            if ($name !== '') {
                $names[(int) $category->getId()] = $name;
            }
        }

        return $names;
    }

    /**
     * @param Context $context
     * @param int $productId
     * @return string
     */
    private function key(Context $context, int $productId): string
    {
        return $context->storeId . '/' . $productId;
    }
}
