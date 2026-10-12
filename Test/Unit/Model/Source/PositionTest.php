<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Source;

use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Model\Source\Position;
use PHPUnit\Framework\TestCase;

class PositionTest extends TestCase
{
    public function testEveryPositionHasAnOptionInTheApiOrder(): void
    {
        $values = array_column((new Position())->toOptionArray(), 'value');

        self::assertSame(PlacementInterface::POSITIONS, $values);
        self::assertCount(21, PlacementInterface::IMAGE_POSITIONS, '9 on the image, 12 in the bands around it');
        self::assertSame(['near_price', 'near_cart', 'under_name'], PlacementInterface::INLINE_POSITIONS);
        self::assertSame(PlacementInterface::POSITIONS, array_merge(PlacementInterface::IMAGE_POSITIONS, PlacementInterface::INLINE_POSITIONS));
    }

    public function testEveryPositionCodeFitsTheColumn(): void
    {
        foreach (PlacementInterface::POSITIONS as $position) {
            self::assertLessThanOrEqual(16, strlen($position), $position);
        }
    }
}
