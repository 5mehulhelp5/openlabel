<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Controller\Adminhtml\Product;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The product edit page's "OpenLabel" section (1.1): saving the product saves the labels assigned by hand and
 * reindexes them.
 *
 * @magentoAppArea adminhtml
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-tab', 'price' => 15], 'product')]
#[DataFixture(DesignFixture::class, [], 'design')]
class SaveLabelsTest extends AbstractBackendController
{
    private ?int $labelId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $label = $this->_objectManager->get(LabelFixture::class)->apply([
            'name' => 'Picked by hand',
            'design_id' => (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId(),
            'conditions_serialized' => json_encode([
                'type' => Combine::class, 'aggregator' => 'all', 'value' => '1',
                'conditions' => [[
                    'type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'no-such-sku',
                ]],
            ]),
        ]);
        $this->labelId = (int) $label->getLabelId();
    }

    protected function tearDown(): void
    {
        if ($this->labelId !== null) {
            Bootstrap::getObjectManager()->get(LabelRepositoryInterface::class)->deleteById($this->labelId);
        }
        parent::tearDown();
    }

    public function testSavingTheProductAssignsTheLabelAndIndexesIt(): void
    {
        $product = $this->_objectManager->get(ProductRepositoryInterface::class)->get('ol-tab');
        $productId = (int) $product->getId();

        $this->save($productId, ['openlabel_touched' => '1', 'openlabel_label_ids' => [(string) $this->labelId]]);

        self::assertSame([$this->labelId], $this->_objectManager->get(ManualAssignments::class)->forProduct($productId));
        self::assertSame([$productId], $this->_objectManager->get(LabelReindexer::class)->productIds((int) $this->labelId));
    }

    /**
     * @param array<string, mixed> $openlabel
     */
    private function save(int $productId, array $openlabel): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue([
            'product' => ['name' => 'OL tab', 'sku' => 'ol-tab', 'price' => 15, 'status' => 1, 'attribute_set_id' => 4,
                'quantity_and_stock_status' => ['qty' => 10, 'is_in_stock' => 1], 'website_ids' => [1]] + $openlabel,
            'set' => 4,
            'type' => 'simple',
        ]);
        $this->dispatch('backend/catalog/product/save/id/' . $productId);
    }
}
