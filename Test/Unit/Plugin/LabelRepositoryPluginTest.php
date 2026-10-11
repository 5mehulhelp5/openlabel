<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Plugin;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelIndexingException;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Plugin\LabelRepositoryPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LabelRepositoryPluginTest extends TestCase
{
    public function testAFailedReindexIsLoggedWithItsCauseAndReportedAsSavedButNotMatched(): void
    {
        $cause = new \RuntimeException("SQLSTATE[42S22]: Unknown column 'ol_price.price'");
        $reindexer = $this->createStub(LabelReindexer::class);
        $reindexer->method('reindex')->willThrowException($cause);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Label #7 "Sale": matching products failed: ' . $cause->getMessage(),
            self::callback(static fn (array $context): bool => $context['label_id'] === 7
                && $context['exception'] === $cause
                && $context['conditions'] === '{"type":"x"}')
        );
        $label = $this->createStub(LabelInterface::class);
        $label->method('getLabelId')->willReturn(7);
        $label->method('getName')->willReturn('Sale');
        $label->method('getConditionsSerialized')->willReturn('{"type":"x"}');

        try {
            (new LabelRepositoryPlugin($reindexer, $logger))->afterSave($this->createStub(LabelRepositoryInterface::class), $label);
            self::fail('expected LabelIndexingException');
        } catch (LabelIndexingException $e) {
            self::assertSame(7, $e->getLabelId());
            self::assertStringContainsString("Unknown column 'ol_price.price'", $e->getMessage());
            self::assertStringContainsString('var/log/openlabel.log', $e->getMessage());
            self::assertSame($cause, $e->getPrevious());
        }
    }

    public function testASuccessfulReindexReturnsTheLabel(): void
    {
        $label = $this->createStub(LabelInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $result = (new LabelRepositoryPlugin($this->createStub(LabelReindexer::class), $logger))
            ->afterSave($this->createStub(LabelRepositoryInterface::class), $label);

        self::assertSame($label, $result);
    }
}
