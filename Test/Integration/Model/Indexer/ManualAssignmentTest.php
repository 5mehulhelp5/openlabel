<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Indexer;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Manual assignment (1.1): products picked by hand on the product page show the label whatever its conditions,
 * within the label's status, store views, customer groups and dates.
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-man-rule', 'price' => 10], 'by_rule')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-man-hand', 'price' => 10], 'by_hand')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-man-off', 'price' => 10, 'status' => Status::STATUS_DISABLED], 'disabled')]
#[DataFixture(DesignFixture::class, [], 'design')]
class ManualAssignmentTest extends TestCase
{
    private ?int $labelId = null;

    protected function setUp(): void
    {
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply([
            'name' => 'Manual',
            'design_id' => (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId(),
            'conditions_serialized' => json_encode([
                'type' => Combine::class, 'aggregator' => 'all', 'value' => '1',
                'conditions' => [[
                    'type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'ol-man-rule',
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
    }

    public function testAProductPickedByHandIsIndexedAndUnpickingRemovesIt(): void
    {
        $om = Bootstrap::getObjectManager();
        $assignments = $om->get(ManualAssignments::class);
        $reindexer = $om->get(LabelReindexer::class);
        [$byRule, $byHand, $disabled] = $this->ids('by_rule', 'by_hand', 'disabled');

        $changed = $assignments->setForProduct($byHand, [$this->labelId]);
        $assignments->setForProduct($disabled, [$this->labelId]);
        $reindexer->reindexLabel((int) $this->labelId);

        self::assertSame([(int) $this->labelId], $changed);
        self::assertSame([$this->labelId], $assignments->forProduct($byHand));
        self::assertEqualsCanonicalizing([$byRule, $byHand], $reindexer->productIds((int) $this->labelId), 'disabled products stay out');

        self::assertSame([(int) $this->labelId], $assignments->setForProduct($byHand, []));
        self::assertSame([], $assignments->setForProduct($byHand, []), 'nothing changed, nothing to reindex');
        $reindexer->reindexLabel((int) $this->labelId);
        self::assertSame([$byRule], $reindexer->productIds((int) $this->labelId));
    }

    public function testAssignmentsDisappearWithTheirLabel(): void
    {
        $om = Bootstrap::getObjectManager();
        [$byHand] = $this->ids('by_hand');
        $om->get(ManualAssignments::class)->setForProduct($byHand, [$this->labelId]);

        $om->get(LabelRepositoryInterface::class)->deleteById((int) $this->labelId);
        $this->labelId = null;

        self::assertSame([], $om->get(ManualAssignments::class)->forProduct($byHand));
    }

    /**
     * @return int[]
     */
    private function ids(string ...$names): array
    {
        $storage = DataFixtureStorageManager::getStorage();

        return array_map(static fn (string $name): int => (int) $storage->get($name)->getId(), $names);
    }
}
