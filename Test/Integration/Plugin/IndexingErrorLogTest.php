<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Plugin;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelIndexingException;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Plugin\LabelRepositoryPlugin;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The plugin as wired in DI writes to var/log/openlabel.log.
 */
class IndexingErrorLogTest extends TestCase
{
    public function testIndexingErrorsGoToOpenLabelLog(): void
    {
        $om = Bootstrap::getObjectManager();
        $marker = 'ol-log-test-' . bin2hex(random_bytes(4));
        $reindexer = $this->createStub(LabelReindexer::class);
        $reindexer->method('reindex')->willThrowException(new \RuntimeException($marker));
        $plugin = $om->create(LabelRepositoryPlugin::class, ['labelReindexer' => $reindexer]);
        $label = $this->createStub(LabelInterface::class);
        $label->method('getLabelId')->willReturn(42);
        $label->method('getName')->willReturn('Logged');

        try {
            $plugin->afterSave($this->createStub(LabelRepositoryInterface::class), $label);
            self::fail('expected LabelIndexingException');
        } catch (LabelIndexingException) {
            // expected
        }

        // Magento's file handlers write below the installation root (like system.log), not the test sandbox.
        $file = BP . '/var/log/openlabel.log';
        self::assertFileExists($file);
        self::assertStringContainsString(
            'openlabel.ERROR: Label #42 "Logged": matching products failed: ' . $marker,
            (string) file_get_contents($file) // phpcs:ignore Magento2.Functions.DiscouragedFunction
        );
    }
}
