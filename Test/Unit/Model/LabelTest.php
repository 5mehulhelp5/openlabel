<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model;

use Iranimij\OpenLabel\Model\Label;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class LabelTest extends TestCase
{
    public function testOneDotOneFieldsDefaultToOff(): void
    {
        $label = $this->label();

        self::assertFalse($label->isHideOnZeroVariable());
        self::assertNull($label->getClickUrl());
        self::assertFalse($label->isClickNofollow());
    }

    public function testOneDotOneFieldsRoundTrip(): void
    {
        $label = $this->label()
            ->setHideOnZeroVariable(true)
            ->setClickUrl('/sale.html')
            ->setClickNofollow(true);

        self::assertTrue($label->isHideOnZeroVariable());
        self::assertSame('/sale.html', $label->getClickUrl());
        self::assertTrue($label->isClickNofollow());
    }

    public function testBlankClickUrlMeansNoLink(): void
    {
        self::assertNull($this->label()->setClickUrl('  ')->getClickUrl());
    }

    private function label(): Label
    {
        /** @var Label $label */
        $label = (new ObjectManager($this))->getObject(Label::class);

        return $label;
    }
}
