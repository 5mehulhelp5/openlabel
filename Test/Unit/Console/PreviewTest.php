<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Console;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Console\Preview;
use Iranimij\OpenLabel\Model\Indexer\IndexReader;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;

class PreviewTest extends TestCase
{
    public function testPrintsTheMatchedCountAndUpToTwentySkus(): void
    {
        $skus = array_map(static fn (int $i): string => 'SKU-' . $i, range(1, 25));
        $reader = $this->createStub(IndexReader::class);
        $reader->method('countProducts')->willReturn(25);
        $reader->method('skus')->willReturn(array_slice($skus, 0, 20));

        $tester = new CommandTester(new Preview($this->repository('Sale'), $reader, $this->storeManager(), $this->appState()));
        $exit = $tester->execute(['label_id' => '3']);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Label #3 "Sale" matches 25 products in store view "default" (1).', $display);
        self::assertStringContainsString('First 20 SKUs:', $display);
        self::assertStringContainsString('SKU-1', $display);
        self::assertStringContainsString('SKU-20', $display);
        self::assertStringNotContainsString('SKU-21', $display);
    }

    public function testExplainsAnEmptyResult(): void
    {
        $reader = $this->createStub(IndexReader::class);
        $reader->method('countProducts')->willReturn(0);
        $reader->method('skus')->willReturn([]);

        $tester = new CommandTester(new Preview($this->repository('Sale'), $reader, $this->storeManager(), $this->appState()));
        $tester->execute(['label_id' => '3', '--store' => '1']);

        self::assertStringContainsString('matches 0 products', $tester->getDisplay());
        self::assertStringContainsString('Run openlabel:reindex 3 if the label was just changed', $tester->getDisplay());
    }

    private function repository(string $name): LabelRepositoryInterface
    {
        $label = $this->createStub(LabelInterface::class);
        $label->method('getName')->willReturn($name);
        $repository = $this->createStub(LabelRepositoryInterface::class);
        $repository->method('getById')->willReturn($label);

        return $repository;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getDefaultStoreView')->willReturn($store);

        return $storeManager;
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
