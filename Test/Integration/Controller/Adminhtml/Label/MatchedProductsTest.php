<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Controller\Adminhtml\Label;

use Iranimij\OpenLabel\Block\Adminhtml\Label\Edit\MatchedProducts;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Matched products section and "Reindex now".
 *
 * @magentoAppArea adminhtml
 */
#[DbIsolation(false)]
class MatchedProductsTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Iranimij_OpenLabel::labels';

    /**
     * @var string
     */
    protected $uri = 'backend/openlabel/label/reindex';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(ProductFixture::class, ['sku' => 'ol-matched-a', 'name' => 'Matched jacket'], 'a')]
    #[DataFixture(ProductFixture::class, ['sku' => 'ol-matched-b'], 'b')]
    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$'], 'label')]
    public function testSectionShowsTheCountAndTheProducts(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $labelId = (int) $storage->get('label')->getLabelId();
        $this->replaceIndex($labelId, [(int) $storage->get('a')->getId(), (int) $storage->get('b')->getId()]);

        $html = $this->section($labelId);

        self::assertStringContainsString('2 products match', $html);
        self::assertStringContainsString('ol-matched-a', $html);
        self::assertStringContainsString('Matched jacket', $html);
        self::assertStringContainsString('openlabel/label/reindex', $html);
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$'], 'label')]
    public function testNoMatchExplainsWhatToCheck(): void
    {
        $labelId = (int) DataFixtureStorageManager::getStorage()->get('label')->getLabelId();
        $this->replaceIndex($labelId, []);

        $html = $this->section($labelId);

        self::assertStringContainsString('No products match yet.', $html);
        self::assertStringContainsString('Use for Promo Rule Conditions', $html);
    }

    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(ProductFixture::class, ['sku' => 'ol-matched-why'], 'product')]
    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, [
        'design_id' => '$design.design_id$',
        'status' => 0,
        'conditions_serialized' => '{"type":"Iranimij\\\\OpenLabel\\\\Model\\\\Rule\\\\Condition\\\\Combine","aggregator":"all","value":"1","conditions":[{"type":"Iranimij\\\\OpenLabel\\\\Model\\\\Rule\\\\Condition\\\\Product","attribute":"sku","operator":"==","value":"ol-matched-why"},{"type":"Iranimij\\\\OpenLabel\\\\Model\\\\Rule\\\\Condition\\\\Product","attribute":"sku","operator":"==","value":"ol-matched-nothing"}]}',
    ], 'label')]
    public function testNoMatchShowsWhyPerCondition(): void
    {
        $labelId = (int) DataFixtureStorageManager::getStorage()->get('label')->getLabelId();
        $this->replaceIndex($labelId, []);

        $html = $this->section($labelId);

        self::assertStringContainsString('Why no products?', $html);
        self::assertStringContainsString('The label is switched off.', $html);
        self::assertMatchesRegularExpression('#SKU is ol-matched-why</span>\s*<span class="ol-matched__condition-count">\s*1 products#', $html);
        self::assertMatchesRegularExpression('#SKU is ol-matched-nothing</span>\s*<span class="ol-matched__condition-count">\s*no products: this alone rules everything out#', $html);
    }

    public function testNewLabelAsksToSaveFirst(): void
    {
        self::assertStringContainsString('Save the label to see which products match.', $this->section(0));
    }

    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(ProductFixture::class, ['sku' => 'ol-matched-new'], 'product')]
    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$'], 'label')]
    public function testReindexNowRebuildsTheLabelAndReportsTheCount(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $labelId = (int) $storage->get('label')->getLabelId();
        $connection = $this->_objectManager->get(ResourceConnection::class)->getConnection();
        $connection->update(
            $connection->getTableName('openlabel_label'),
            ['conditions_serialized' => json_encode(['type' => Combine::class, 'aggregator' => 'all', 'value' => '1',
                'conditions' => [['type' => IsNew::class, 'attribute' => 'days_since_created', 'operator' => '<=', 'value' => '30']]])],
            ['label_id = ?' => $labelId]
        );
        $this->replaceIndex($labelId, []);

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue(['id' => (string) $labelId]);
        $this->dispatch($this->uri);

        self::assertRedirect(self::stringContains('openlabel/label/edit/id/' . $labelId));
        $this->assertSessionMessages(
            self::callback(static fn (array $messages): bool => preg_grep(
                '/^Reindexed\. \d+ products? match(es)? in the default store view\.$/',
                $messages
            ) !== []),
            MessageInterface::TYPE_SUCCESS
        );
        $count = (int) $connection->fetchOne($connection->select()
            ->from($connection->getTableName('openlabel_index'), 'COUNT(DISTINCT product_id)')
            ->where('label_id = ?', $labelId)
            ->where('product_id = ?', (int) $storage->get('product')->getId()));
        self::assertSame(1, $count, 'the new product is in the index again');
    }

    private function section(int $labelId): string
    {
        $this->_objectManager->get(RequestInterface::class)->setParam('id', $labelId === 0 ? null : (string) $labelId);
        $block = $this->_objectManager->get(LayoutInterface::class)->createBlock(MatchedProducts::class);
        $block->setTemplate('Iranimij_OpenLabel::label/matched-products.phtml');

        return $block->toHtml();
    }

    /**
     * @param int[] $productIds
     */
    private function replaceIndex(int $labelId, array $productIds): void
    {
        $connection = $this->_objectManager->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('openlabel_index');
        $connection->delete($table, ['label_id = ?' => $labelId]);
        foreach ($productIds as $productId) {
            $connection->insert($table, [
                'label_id' => $labelId, 'product_id' => $productId, 'store_id' => 1,
                'customer_group_id' => -1, 'priority' => 0,
            ]);
        }
    }
}
