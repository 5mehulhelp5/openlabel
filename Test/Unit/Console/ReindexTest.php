<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Console;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Console\Reindex;
use Iranimij\OpenLabel\Model\Indexer\Diff;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;

class ReindexTest extends TestCase
{
    public function testFullReindexGoesThroughTheIndexerAndReportsRowsAndTime(): void
    {
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->expects(self::once())->method('reindexAll');
        $registry = $this->createMock(IndexerRegistry::class);
        $registry->method('get')->with('openlabel_product')->willReturn($indexer);
        $reindexer = $this->createStub(LabelReindexer::class);
        $reindexer->method('countRows')->willReturn(2480);

        $tester = new CommandTester(new Reindex($registry, $reindexer, $this->createStub(LabelRepositoryInterface::class), $this->appState()));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertMatchesRegularExpression('/Full reindex done: 2,480 index rows in \d+\.\d{2} s\./', $tester->getDisplay());
    }

    public function testSingleLabelReindexReportsTheDiff(): void
    {
        $label = $this->createStub(LabelInterface::class);
        $label->method('getName')->willReturn('Sale');
        $repository = $this->createMock(LabelRepositoryInterface::class);
        $repository->method('getById')->with(3)->willReturn($label);
        $reindexer = $this->createMock(LabelReindexer::class);
        $reindexer->expects(self::once())->method('reindexLabel')->with(3)->willReturn(new Diff([1, 2, 3], [2, 3, 4, 5]));
        $reindexer->method('productIds')->willReturn([2, 3, 4, 5]);

        $tester = new CommandTester(new Reindex($this->createStub(IndexerRegistry::class), $reindexer, $repository, $this->appState()));
        $exit = $tester->execute(['label_id' => '3']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertMatchesRegularExpression('/Label #3 "Sale": 4 products \(\+2 \/ -1\) in \d+\.\d{2} s\./', $tester->getDisplay());
    }

    public function testUnknownLabelFails(): void
    {
        $repository = $this->createStub(LabelRepositoryInterface::class);
        $repository->method('getById')->willThrowException(new NoSuchEntityException(__('Label with ID "9" does not exist.')));

        $tester = new CommandTester(new Reindex($this->createStub(IndexerRegistry::class), $this->createStub(LabelReindexer::class), $repository, $this->appState()));
        $exit = $tester->execute(['label_id' => '9']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Label with ID "9" does not exist.', $tester->getDisplay());
    }

    private function appState(): State
    {
        $state = $this->createMock(State::class);
        $state->method('emulateAreaCode')->willReturnCallback(
            static function (string $area, callable $callback, array $params = []) {
                self::assertSame('adminhtml', $area);

                return $callback(...$params);
            }
        );

        return $state;
    }
}
