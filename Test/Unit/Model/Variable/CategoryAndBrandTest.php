<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Variable;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Processor\Attr;
use Iranimij\OpenLabel\Model\Variable\Processor\Brand;
use Iranimij\OpenLabel\Model\Variable\Processor\CategoryName;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * {BRAND} and the category-page branch of {CATEGORY_NAME}; the product-category lookup is covered by the
 * integration RendererTest.
 */
class CategoryAndBrandTest extends TestCase
{
    private string $brandAttribute = 'manufacturer';

    /** @var array<int, array{product_id: int, category_id: int}> category assignments, deepest first */
    private array $assignments = [];

    /** @var array<int, string> active categories by id */
    private array $activeCategories = [];

    private int $queries = 0;

    /** @var array<int, array{string, array<int, mixed>}> */
    private array $selectCalls = [];

    /** @var int[][] category ids the name lookups asked for */
    private array $nameLookups = [];

    public function testBrandReadsTheConfiguredAttributeForTheStore(): void
    {
        $product = $this->product(['manufacturer' => 'Acme', 'brand' => 'Zeta']);

        self::assertSame('Acme', $this->brand()->getValue($product, new Context(1, 1, 0))->raw);
        $this->brandAttribute = 'brand';
        self::assertSame('Zeta', $this->brand()->getValue($product, new Context(1, 1, 0))->raw);
    }

    public function testBrandIsEmptyWithoutTheAttributeOrAConfiguredCode(): void
    {
        self::assertTrue($this->brand()->getValue($this->product([]), new Context(1, 1, 0))->isEmpty());
        $this->brandAttribute = ' ';
        self::assertTrue($this->brand()->getValue($this->product(['manufacturer' => 'Acme']), new Context(1, 1, 0))->isEmpty());
    }

    public function testCategoryNameOnACategoryPageIsTheCategoryBeingBrowsed(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Jackets');
        $registry = new Registry();
        $registry->register('current_category', $category);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        $value = $this->categoryName($registry, $resource)->getValue($this->product(['entity_id' => 5]), new Context(1, 1, 0));

        self::assertSame(Value::TEXT, $value->type);
        self::assertSame('Jackets', $value->raw);
    }

    public function testCategoryNameIsEmptyForAProductWithoutAnId(): void
    {
        $value = $this->categoryName(new Registry(), $this->createStub(ResourceConnection::class))
            ->getValue($this->product([]), new Context(1, 1, 0));

        self::assertTrue($value->isEmpty());
    }

    public function testAListingIsLoadedWithOneQueryAndTheFirstActiveCategoryWins(): void
    {
        $this->assignments = [
            ['product_id' => 5, 'category_id' => 30],
            ['product_id' => 5, 'category_id' => 20],
            ['product_id' => 5, 'category_id' => 10],
            ['product_id' => 6, 'category_id' => 30],
        ];
        $this->activeCategories = [20 => 'Jackets', 10 => 'Clothing'];
        $processor = $this->categoryName(new Registry(), $this->resource());
        $context = new Context(1, 1, 0);

        $processor->preload([$this->product(['entity_id' => 5]), $this->product(['entity_id' => 6])], $context);

        self::assertSame('Jackets', $processor->getValue($this->product(['entity_id' => 5]), $context)->raw, 'category 30 is inactive');
        self::assertTrue($processor->getValue($this->product(['entity_id' => 6]), $context)->isEmpty(), 'only an inactive category');
        self::assertSame(1, $this->queries);
        self::assertSame([[30, 20, 10]], $this->nameLookups);
        self::assertContains(['where', ['c.path LIKE ?', '1/2/%']], $this->selectCalls, 'the store\'s tree only');
        self::assertContains(['where', ['cp.product_id IN (?)', [5, 6], \Zend_Db::INT_TYPE]], $this->selectCalls);
        self::assertContains(['order', [['cp.product_id', 'c.level DESC', 'cp.position', 'c.entity_id']]], $this->selectCalls);

        $processor->preload([$this->product(['entity_id' => 5])], $context);
        self::assertSame(1, $this->queries, 'loaded products are not looked up again');
    }

    public function testAProductOutsideAListingIsLookedUpOnItsOwn(): void
    {
        $this->assignments = [['product_id' => 7, 'category_id' => 10]];
        $this->activeCategories = [10 => 'Clothing'];
        $registry = new Registry();
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Sale');
        $registry->register('current_category', $category);
        $registry->register('current_product', $this->product(['entity_id' => 7]));

        $value = $this->categoryName($registry, $this->resource())->getValue($this->product(['entity_id' => 7]), new Context(1, 1, 0));

        self::assertSame('Clothing', $value->raw, 'a product page ignores the category the shopper came from');
        self::assertSame(1, $this->queries);
    }

    public function testNoCategoriesNoNameLookup(): void
    {
        $processor = $this->categoryName(new Registry(), $this->resource());

        self::assertTrue($processor->getValue($this->product(['entity_id' => 8]), new Context(1, 1, 0))->isEmpty());
        self::assertSame([], $this->nameLookups);
    }

    public function testAStoreWithoutRootCategoryHasNoCategoryName(): void
    {
        $processor = $this->categoryName(new Registry(), $this->resource(), 0);

        self::assertTrue($processor->getValue($this->product(['entity_id' => 8]), new Context(1, 1, 0))->isEmpty());
        self::assertSame(0, $this->queries);
    }

    private function resource(): ResourceConnection
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'join', 'where', 'order'] as $method) {
            $select->method($method)->willReturnCallback(function (mixed ...$args) use ($method, &$select): Select {
                while ($args !== [] && end($args) === null) {
                    array_pop($args);
                }
                $this->selectCalls[] = [$method, $args];

                return $select;
            });
        }
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(function (): array {
            $this->queries++;

            return $this->assignments;
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $resource;
    }

    private function brand(): Brand
    {
        $config = $this->createStub(TypedReader::class);
        $config->method('getString')->willReturnCallback(fn (): string => $this->brandAttribute);
        $eavConfig = $this->createStub(EavConfig::class);
        $eavConfig->method('getAttribute')->willThrowException(new \RuntimeException('not an option attribute'));

        return new Brand(new Attr($eavConfig), $config);
    }

    private function categoryName(Registry $registry, ResourceConnection $resource, int $rootCategoryId = 2): CategoryName
    {
        $store = $this->createStub(Store::class);
        $store->method('getRootCategoryId')->willReturn($rootCategoryId);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(fn (): Collection => $this->categoryCollection());

        return new CategoryName($registry, $resource, $factory, $storeManager);
    }

    private function categoryCollection(): Collection
    {
        $ids = [];
        $collection = $this->createStub(Collection::class);
        foreach (['setStoreId', 'addAttributeToSelect', 'addIsActiveFilter'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('addIdFilter')->willReturnCallback(function (array $categoryIds) use (&$ids, $collection): Collection {
            $ids = $categoryIds;
            $this->nameLookups[] = $categoryIds;

            return $collection;
        });
        $collection->method('getIterator')->willReturnCallback(function () use (&$ids): \ArrayIterator {
            $categories = [];
            foreach (array_intersect_key($this->activeCategories, array_flip($ids)) as $id => $name) {
                $category = $this->createStub(Category::class);
                $category->method('getId')->willReturn($id);
                $category->method('getName')->willReturn($name);
                $categories[] = $category;
            }

            return new \ArrayIterator($categories);
        });

        return $collection;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function product(array $data): Product
    {
        /** @var Product $product */
        $product = (new ObjectManager($this))->getObject(Product::class);
        $product->setData($data);

        return $product;
    }
}
