<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model;

use Iranimij\OpenLabel\Model\Design;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class DesignTest extends TestCase
{
    public function testOneDotOneFieldsDefaultToNone(): void
    {
        $design = $this->design();

        self::assertSame('none', $design->getAnimation());
        self::assertSame('none', $design->getHoverEffect());
        self::assertSame([], $design->getSizes());
    }

    public function testSizesAreStoredAsJsonAndReadBackAsIntegers(): void
    {
        $design = $this->design()->setSizes(['sm' => ['width' => '24', 'font_size' => 11], 'lg' => ['width' => 14]]);

        self::assertSame('{"sm":{"width":24,"font_size":11},"lg":{"width":14}}', $design->getData('sizes'));
        self::assertSame(['sm' => ['width' => 24, 'font_size' => 11], 'lg' => ['width' => 14]], $design->getSizes());
    }

    public function testEmptySizesAreStoredAsNull(): void
    {
        $design = $this->design()->setSizes(['sm' => ['width' => 24]])->setSizes([]);

        self::assertNull($design->getData('sizes'));
    }

    public function testBrokenSizesJsonReadsAsNoOverrides(): void
    {
        $design = $this->design();
        $design->setData('sizes', '{broken');

        self::assertSame([], $design->getSizes());
    }

    public function testAnimationAndHoverEffectRoundTrip(): void
    {
        $design = $this->design()->setAnimation('fade')->setHoverEffect('lift');

        self::assertSame('fade', $design->getAnimation());
        self::assertSame('lift', $design->getHoverEffect());
    }

    private function design(): Design
    {
        /** @var Design $design */
        $design = (new ObjectManager($this))->getObject(Design::class);

        return $design;
    }
}
