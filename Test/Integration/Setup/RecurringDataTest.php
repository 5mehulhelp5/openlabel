<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Setup;

use Iranimij\OpenLabel\Model\Css\Storage;
use Iranimij\OpenLabel\Setup\RecurringData;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Upgrades bring new structural CSS (1.1: bands, text positions, motion, tooltips): every setup:upgrade rebuilds the
 * label stylesheet, so stores never keep serving the previous version's file.
 */
class RecurringDataTest extends TestCase
{
    public function testSetupUpgradeRebuildsTheStylesheetWithTheCurrentStructuralCss(): void
    {
        $om = Bootstrap::getObjectManager();
        $media = $om->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA);
        $storage = $om->get(Storage::class);
        // A stylesheet of an older version, still the current one for store 1.
        $storage->write(1, '/* OpenLabel 1.0 */ .ol-stack{position:absolute}');

        $om->create(RecurringData::class)->install(
            $this->createStub(ModuleDataSetupInterface::class),
            $this->createStub(ModuleContextInterface::class)
        );

        $css = $media->readFile((string) $storage->current(1));
        self::assertStringContainsString('.ol-pos-otc', $css, 'bands around the image');
        self::assertStringContainsString('.ol-inline', $css, 'labels in the product text');
        self::assertStringNotContainsString('OpenLabel 1.0', $css);
    }
}
