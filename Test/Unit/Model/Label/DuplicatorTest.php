<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterfaceFactory;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Label;
use Iranimij\OpenLabel\Model\Label\Duplicator;
use Iranimij\OpenLabel\Model\LabelFactory;
use Iranimij\OpenLabel\Model\Placement;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class DuplicatorTest extends TestCase
{
    public function testCopyIsDisabledRenamedAndKeepsPlacementsAndConditions(): void
    {
        $om = new ObjectManager($this);
        /** @var Label $source */
        $source = $om->getObject(Label::class);
        $source->setData(['label_id' => 5, 'name' => 'Sale', 'status' => 1, 'priority' => 2, 'design_id' => 3,
            'conditions_serialized' => '{"type":"combine"}', 'store_ids' => [1], 'customer_group_ids' => [0],
            'valid_from' => '2026-11-01 00:00:00', 'apply_to_parent' => true,
            'hide_on_zero_variable' => true, 'click_url' => '/sale.html', 'click_nofollow' => true]);
        $placement = $om->getObject(Placement::class)->setArea('listing')->setPosition('tr')->setGap(6);
        $placement->setPlacementId(44)->setLabelId(5);
        $source->setPlacements([$placement]);

        $labels = $this->createMock(LabelFactory::class);
        $labels->method('create')->willReturnCallback(fn () => $om->getObject(Label::class));
        $placements = $this->createMock(PlacementInterfaceFactory::class);
        $placements->method('create')->willReturnCallback(fn () => $om->getObject(Placement::class));
        $repository = $this->createMock(LabelRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->willReturnArgument(0);

        $copy = (new Duplicator($labels, $placements, $repository))->duplicate($source);

        self::assertNull($copy->getLabelId());
        self::assertSame('Sale (copy)', $copy->getName());
        self::assertSame(LabelInterface::STATUS_DISABLED, $copy->getStatus(), 'a copy never goes live by surprise');
        self::assertSame([2, 3, '{"type":"combine"}', [1], [0], '2026-11-01 00:00:00', true], [
            $copy->getPriority(), $copy->getDesignId(), $copy->getConditionsSerialized(), $copy->getStoreIds(),
            $copy->getCustomerGroupIds(), $copy->getValidFrom(), $copy->isApplyToParent(),
        ]);
        self::assertSame([true, '/sale.html', true], [
            $copy->isHideOnZeroVariable(), $copy->getClickUrl(), $copy->isClickNofollow(),
        ], '1.1 fields are copied too');
        self::assertCount(1, $copy->getPlacements());
        self::assertNull($copy->getPlacements()[0]->getPlacementId());
        self::assertSame(['listing', 'tr', 6], [
            $copy->getPlacements()[0]->getArea(), $copy->getPlacements()[0]->getPosition(), $copy->getPlacements()[0]->getGap(),
        ]);
        self::assertNotSame($placement, $copy->getPlacements()[0]);
    }
}
