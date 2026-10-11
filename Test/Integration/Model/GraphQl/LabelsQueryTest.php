<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\GraphQl;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\GraphQl\LabelsResolver;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Iranimij\OpenLabel\Test\Integration\Helper\QueryCounter;
use Laminas\Http\Headers;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchRequestItemInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Request as WebapiRequest;
use Magento\GraphQl\Controller\GraphQl;
use Magento\GraphQl\Model\Query\ContextFactory;
use Magento\GraphQlCache\Model\CacheableQuery;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Model\Authorization\TokenUserContext;
use PHPUnit\Framework\TestCase;

/**
 * `openlabel_labels` on ProductInterface through the real GraphQL controller. Products are reached through `route`
 (no search engine involved, so the 2.4.7 CI search index does not matter).
 */
#[AppArea(Area::AREA_GRAPHQL)]
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(StoreFixture::class, ['code' => 'ol_gql'], 'store2')]
#[DataFixture(DesignFixture::class, ['store_texts' => [0 => ['text' => 'Sale {SKU}'], 1 => ['text' => 'Angebot<br>{SKU}']]], 'design')]
#[DataFixture(CustomerFixture::class, ['email' => 'ol-gql-wholesale@example.com', 'group_id' => 2], 'wholesale')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-gql-a', 'price' => 10], 'a')]
#[DataFixture(AttributeFixture::class, as: 'attr')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-gql-child', 'price' => 20, '$attr.attribute_code$' => '$attr.option_1$'], 'child')]
#[DataFixture(ConfigurableFixture::class, ['sku' => 'ol-gql-conf', '_options' => ['$attr$'], '_links' => ['$child$']], 'conf')]
class LabelsQueryTest extends TestCase
{
    private const LIST_SIZE = 20;

    /** @var LabelInterface[] */
    private ?array $created = [];

    protected function tearDown(): void
    {
        // Plain SQL, no cache cleaning or CSS rebuild needed; placements go with the label (foreign key). Note: when a
        // test errors, the framework reverts the class fixtures before this runs.
        $ids = array_map(static fn (LabelInterface $label): int => (int) $label->getLabelId(), $this->created ?? []);
        if ($ids !== []) {
            $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
            $connection = $resource->getConnection();
            $connection->delete($resource->getTableName('openlabel_index'), ['label_id IN (?)' => $ids]);
            $connection->delete($resource->getTableName('openlabel_label'), ['label_id IN (?)' => $ids]);
        }
        $this->created = [];
    }

    public function testTextLabelAsStructuredDataInTheDefaultStore(): void
    {
        $label = $this->label('ol-gql-a', ['priority' => 2, 'placements' => [
            ['area' => 'listing', 'position' => 'tr'],
            ['area' => 'product', 'position' => 'bl'],
        ]]);
        $designId = (int) $this->fixture('design')->getDesignId();

        $labels = $this->labelsOf('ol-gql-a');

        self::assertSame([
            [
                'id' => (int) $label->getLabelId(), 'name' => 'GraphQL ol-gql-a', 'type' => 'text',
                'text' => "Angebot\nol-gql-a", 'image_url' => null, 'alt' => null, 'position' => 'tr',
                'area' => 'listing', 'css_class' => 'ol-label ol-label--text ol-d-' . $designId . ' ol-shape-pill',
                'bg_color' => '#e11d48', 'text_color' => '#ffffff', 'priority' => 2,
            ],
            [
                'id' => (int) $label->getLabelId(), 'name' => 'GraphQL ol-gql-a', 'type' => 'text',
                'text' => "Angebot\nol-gql-a", 'image_url' => null, 'alt' => null, 'position' => 'bl',
                'area' => 'product', 'css_class' => 'ol-label ol-label--text ol-d-' . $designId . ' ol-shape-pill',
                'bg_color' => '#e11d48', 'text_color' => '#ffffff', 'priority' => 2,
            ],
        ], $labels);
    }

    public function testStoreHeaderPicksTheStoreViewText(): void
    {
        $this->label('ol-gql-a');

        $labels = $this->labelsOf('ol-gql-a', ['Store' => 'ol_gql']);

        self::assertSame(['Sale ol-gql-a'], array_column($labels, 'text'));
    }

    public function testDisabledAndExpiredLabelsAreNotExposed(): void
    {
        $this->label('ol-gql-a', ['status' => LabelInterface::STATUS_DISABLED]);
        $this->label('ol-gql-a', ['valid_to' => '2020-01-01 00:00:00']);

        self::assertSame([], $this->labelsOf('ol-gql-a'));
    }

    public function testGroupRestrictedLabelOnlyForThatGroup(): void
    {
        $label = $this->label('ol-gql-a', ['customer_group_ids' => [2]]);

        self::assertSame([], $this->labelsOf('ol-gql-a'), 'guests do not see a Wholesale label');

        $token = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class)
            ->createCustomerAccessToken('ol-gql-wholesale@example.com', 'password');
        self::assertSame(
            [(int) $label->getLabelId()],
            array_column($this->labelsOf('ol-gql-a', ['Authorization' => 'Bearer ' . $token]), 'id')
        );
    }

    public function testConfigurableParentShowsTheLabelOfAMatchingChild(): void
    {
        $label = $this->label('ol-gql-child', ['apply_to_parent' => true]);

        self::assertSame([(int) $label->getLabelId()], array_column($this->labelsOf('ol-gql-conf'), 'id'));
    }

    public function testCachedGetResponseCarriesLabelAndDesignTags(): void
    {
        $label = $this->label('ol-gql-a');
        $cacheable = Bootstrap::getObjectManager()->get(CacheableQuery::class);
        $cacheable->_resetState();

        $this->send($this->routeQuery('ol-gql-a'), [], 'GET');

        // The tags core turns into X-Magento-Tags (the built-in page cache strips the header from the response).
        self::assertTrue($cacheable->isCacheable());
        self::assertContains('openlabel_' . $label->getLabelId(), $cacheable->getCacheTags());
        self::assertContains('openlabel_design_' . (int) $this->fixture('design')->getDesignId(), $cacheable->getCacheTags());
    }

    /**
     * The batch resolver itself, with 20 products loaded like a listing: one label query for all of them.
     */
    public function testTwentyProductsCostOneLabelQuery(): void
    {
        $om = Bootstrap::getObjectManager();
        $productFixture = $om->get(ProductFixture::class);
        $skus = [];
        for ($i = 1; $i <= self::LIST_SIZE; $i++) {
            $sku = sprintf('ol-gql-item-%02d', $i);
            $skus[] = $sku;
            if (!$this->exists($sku)) {
                $productFixture->apply(['sku' => $sku, 'price' => 5]);
            }
        }
        $this->label(implode(', ', $skus));
        $products = $om->create(ProductCollection::class)->addAttributeToSelect('*')->addAttributeToFilter('sku', ['in' => $skus]);
        $requests = array_map(fn (ProductInterface $product): BatchRequestItemInterface => $this->batchItem($product), array_values($products->getItems()));
        $context = $om->get(ContextFactory::class)->create();
        $field = $om->create(Field::class, ['name' => 'openlabel_labels', 'type' => 'OpenLabelLabel', 'required' => true, 'isList' => true]);

        $counter = $om->get(QueryCounter::class);
        $response = null;
        $counter->count(function () use ($om, $context, $field, $requests, &$response): void {
            $response = $om->get(LabelsResolver::class)->resolve($context, $field, $requests);
        });

        self::assertCount(self::LIST_SIZE, $requests);
        foreach ($requests as $request) {
            $sku = $request->getValue()['model']->getSku();
            self::assertSame(["Angebot\n" . $sku], array_column($response->findResponseFor($request), 'text'));
        }
        $indexQueries = array_filter($counter->last, static fn (string $sql): bool => str_contains($sql, 'openlabel_index'));
        self::assertCount(1, $indexQueries, implode("\n", $indexQueries));
    }

    private function batchItem(ProductInterface $product): BatchRequestItemInterface
    {
        return new class ($product) implements BatchRequestItemInterface {
            public function __construct(private readonly ProductInterface $product)
            {
            }

            public function getInfo(): ResolveInfo
            {
                throw new \LogicException('not used by the label resolver');
            }

            public function getValue(): ?array
            {
                return ['model' => $this->product];
            }

            public function getArgs(): ?array
            {
                return [];
            }
        };
    }

    /**
     * @param string $skus comma separated SKUs the label matches
     * @param array<string, mixed> $data
     */
    private function label(string $skus, array $data = []): LabelInterface
    {
        $tree = Bootstrap::getObjectManager()->get(Json::class)->serialize([
            'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '()', 'value' => $skus],
            ],
        ]);
        /** @var LabelInterface $label */
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply(array_merge([
            'name' => 'GraphQL ' . $skus,
            'design_id' => (int) $this->fixture('design')->getDesignId(),
            'conditions_serialized' => $tree,
        ], $data));
        $this->created[] = $label;

        return $label;
    }

    /**
     * @param string $sku
     * @param array<string, string> $headers
     * @return array<int, array<string, mixed>>
     */
    private function labelsOf(string $sku, array $headers = []): array
    {
        $fields = 'id name type text image_url alt position area css_class bg_color text_color priority';
        $query = sprintf(
            '{ route(url: "%s") { ... on ProductInterface { sku openlabel_labels { %s } } } }',
            $this->urlOf($sku),
            $fields
        );
        $output = $this->decode($this->send($query, $headers));
        self::assertArrayNotHasKey('errors', $output, json_encode($output) ?: '');
        self::assertSame($sku, $output['data']['route']['sku'] ?? null, json_encode($output) ?: '');

        return $output['data']['route']['openlabel_labels'];
    }

    private function routeQuery(string $sku): string
    {
        return sprintf('{ route(url: "%s") { ... on ProductInterface { openlabel_labels { id } } } }', $this->urlOf($sku));
    }

    /**
     * @param string $query
     * @param array<string, string> $headers
     * @param string $method
     * @return HttpResponse
     */
    private function send(string $query, array $headers = [], string $method = 'POST'): HttpResponse
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(HttpRequest::class);
        $om->removeSharedInstance(WebapiRequest::class);
        $om->removeSharedInstance(GraphQl::class);
        $om->removeSharedInstance(HttpResponse::class);
        // Who is asking is cached per process: forget the previous request's customer.
        $om->removeSharedInstance(UserContextInterface::class);
        $om->removeSharedInstance(TokenUserContext::class);
        /** @var HttpRequest $request */
        $request = $om->get(HttpRequest::class);
        $request->setPathInfo('/graphql');
        $request->setMethod($method);
        if ($method === 'GET') {
            $request->setQueryValue('query', $query);
        } else {
            $request->setContent((string) json_encode(['query' => $query, 'variables' => null, 'operationName' => null]));
        }
        $all = array_merge(['Content-Type' => 'application/json'], $headers);
        $webapiHeaders = $om->get(WebapiRequest::class)->getHeaders();
        foreach ($all as $name => $value) {
            $webapiHeaders->addHeaderLine($name, $value);
        }
        $request->setHeaders($om->create(Headers::class)->addHeaders($all));

        /** @var HttpResponse $response */
        $response = $om->get(GraphQl::class)->dispatch($request);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(HttpResponse $response): array
    {
        return json_decode((string) $response->getContent(), true) ?: [];
    }

    private function urlOf(string $sku): string
    {
        return Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get($sku)->getUrlKey() . '.html';
    }

    private function exists(string $sku): bool
    {
        try {
            Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get($sku);

            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return false;
        }
    }

    private function fixture(string $name): \Magento\Framework\DataObject
    {
        return DataFixtureStorageManager::getStorage()->get($name);
    }
}
