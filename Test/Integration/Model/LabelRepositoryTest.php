<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterfaceFactory;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterfaceFactory;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class LabelRepositoryTest extends TestCase
{
    private ?LabelRepositoryInterface $repository = null;
    private ?LabelInterfaceFactory $labelFactory = null;
    private ?PlacementInterfaceFactory $placementFactory = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->repository = $om->get(LabelRepositoryInterface::class);
        $this->labelFactory = $om->get(LabelInterfaceFactory::class);
        $this->placementFactory = $om->get(PlacementInterfaceFactory::class);
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testSaveStoresPlacementsScopeAndScheduleAndLoadsThemBack(): void
    {
        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $label = $this->labelFactory->create();
        $label->setName('Sale')->setDesignId($designId)->setPriority(2)
            ->setStoreIds([1])->setCustomerGroupIds([0, 1])
            ->setValidFrom('2026-11-20 00:00:00')->setValidTo('2026-11-30 23:59:59')
            ->setApplyToParent(true)->setHideLowerPriority(true)
            ->setConditionsSerialized('{"type":"x"}')
            ->setHideOnZeroVariable(true)->setClickUrl('https://shop.test/sale')->setClickNofollow(true);
        $label->setPlacements([
            $this->placement(['area' => 'product', 'position' => 'br', 'max_labels' => 2, 'gap' => 8]),
            $this->placement(['area' => 'listing', 'position' => 'tl', 'design_id' => $designId, 'offset_x' => -4]),
        ]);

        $saved = $this->repository->save($label);
        $loaded = $this->repository->getById((int) $saved->getLabelId());

        self::assertSame('Sale', $loaded->getName());
        self::assertSame([1], $loaded->getStoreIds());
        self::assertSame([0, 1], $loaded->getCustomerGroupIds());
        self::assertSame('2026-11-20 00:00:00', $loaded->getValidFrom());
        self::assertTrue($loaded->isApplyToParent());
        self::assertTrue($loaded->isHideLowerPriority());
        self::assertSame('{"type":"x"}', $loaded->getConditionsSerialized());
        self::assertTrue($loaded->isHideOnZeroVariable());
        self::assertSame('https://shop.test/sale', $loaded->getClickUrl());
        self::assertTrue($loaded->isClickNofollow());
        $placements = $loaded->getPlacements();
        self::assertCount(2, $placements);
        self::assertSame(['product', 'listing'], array_map(static fn (PlacementInterface $p) => $p->getArea(), $placements));
        self::assertSame($loaded->getLabelId(), $placements[0]->getLabelId());
        self::assertNotNull($placements[0]->getPlacementId());
        self::assertSame(2, $placements[0]->getMaxLabels());
        self::assertSame($designId, $placements[1]->getDesignId());
        self::assertSame(-4, $placements[1]->getOffsetX());
        self::assertNull($placements[0]->getDesignId());
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$', 'placements' => [['area' => 'listing', 'position' => 'tl'], ['area' => 'product', 'position' => 'tr']]], 'label')]
    public function testResaveReplacesPlacements(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('label')->getLabelId();
        $label = $this->repository->getById($id);
        self::assertCount(2, $label->getPlacements());

        $label->setPlacements([$this->placement(['area' => 'product', 'position' => 'bc'])]);
        $this->repository->save($label);

        $reloaded = $this->repository->getById($id);
        self::assertCount(1, $reloaded->getPlacements());
        self::assertSame('bc', $reloaded->getPlacements()[0]->getPosition());
        self::assertSame(1, $this->placementRows($id));
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$'], 'label')]
    public function testDeleteRemovesPlacements(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('label')->getLabelId();

        self::assertTrue($this->repository->deleteById($id));

        self::assertSame(0, $this->placementRows($id));
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById($id);
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$', 'name' => 'On', 'status' => 1], 'on')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$', 'name' => 'Off', 'status' => 0], 'off')]
    public function testGetListFiltersByStatusAndLoadsPlacements(): void
    {
        $criteria = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class)
            ->addFilter(LabelInterface::STATUS, LabelInterface::STATUS_DISABLED)
            ->create();

        $result = $this->repository->getList($criteria);

        self::assertSame(1, $result->getTotalCount());
        $items = $result->getItems();
        self::assertSame('Off', reset($items)->getName());
        self::assertCount(1, reset($items)->getPlacements());
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testSaveRejectsALabelWithoutPlacement(): void
    {
        $label = $this->labelFactory->create();
        $label->setName('Lonely')->setDesignId((int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId());

        try {
            $this->repository->save($label);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertSame(
                ['Pick at least one placement so the label appears somewhere.'],
                array_map(static fn (\Throwable $error): string => $error->getMessage(), $e->getErrors())
            );
        }
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testSaveRejectsUnknownStoreViewsAndCustomerGroups(): void
    {
        $label = $this->labelFactory->create();
        $label->setName('Nowhere')->setDesignId((int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId())
            ->setPlacements([$this->placement()])->setStoreIds([1, 9999])->setCustomerGroupIds([0, 8888]);

        try {
            $this->repository->save($label);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertSame(
                ['Store view 9999 does not exist.', 'Customer group 8888 does not exist.'],
                array_map(static fn (\Throwable $error): string => $error->getMessage(), $e->getErrors())
            );
        }
    }

    public function testSaveRejectsAnUnknownDesign(): void
    {
        $label = $this->labelFactory->create();
        $label->setName('Orphan')->setDesignId(999999)->setPlacements([$this->placement()]);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Design with ID "999999" does not exist.');

        $this->repository->save($label);
    }

    public function testDeleteByIdThrowsForUnknownLabel(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository->deleteById(999999);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function placement(array $data = []): PlacementInterface
    {
        $placement = $this->placementFactory->create();
        $placement->setData(array_merge(['area' => 'listing', 'position' => 'tl'], $data));

        return $placement;
    }

    private function placementRows(int $labelId): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($resource->getTableName('openlabel_placement'), 'COUNT(*)')->where('label_id = ?', $labelId)
        );
    }
}
