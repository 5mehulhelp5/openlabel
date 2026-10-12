<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Label;

use Iranimij\OpenLabel\Model\Label\PlacementSummary;
use PHPUnit\Framework\TestCase;

class PlacementSummaryTest extends TestCase
{
    public function testFormatsAreaAndPositionInOrder(): void
    {
        self::assertSame('Listing TR, Product TL', (new PlacementSummary())->format('listing:tr,product:tl'));
    }

    public function testPositionsAroundTheImageAndInlineReadAsWords(): void
    {
        self::assertSame(
            'Listing above centre, Product next to the price, Listing under the name',
            (new PlacementSummary())->format('listing:above_c,product:near_price,listing:under_name')
        );
    }

    public function testEmptyMeansNowhere(): void
    {
        self::assertSame('Nowhere yet', (new PlacementSummary())->format(null));
        self::assertSame('Nowhere yet', (new PlacementSummary())->format(''));
    }

    public function testUnknownAreaIsShownAsIs(): void
    {
        self::assertSame('Checkout BR', (new PlacementSummary())->format('checkout:br'));
    }

    public function testItemListsAndEmailsReadAsWords(): void
    {
        self::assertSame(
            'Cart, Mini cart, Wish list, Compare, Emails',
            (new PlacementSummary())->format('cart:under_name,minicart:under_name,wishlist:under_name,compare:under_name,email:under_name')
        );
    }
}
