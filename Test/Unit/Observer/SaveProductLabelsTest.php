<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Observer;

use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Observer\SaveProductLabels;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class SaveProductLabelsTest extends TestCase
{
    /** @var array<int, array{int, int[]}> */
    private array $saved = [];

    /** @var int[] */
    private array $reindexed = [];

    private bool $allowed = true;

    public function testTheAssignedLabelsAreSavedAndTheChangedOnesReindexed(): void
    {
        $this->observer()->execute($this->event(['openlabel_touched' => '1', 'openlabel_label_ids' => ['3', '5']]));

        self::assertSame([[42, [3, 5]]], $this->saved);
        self::assertSame([5], $this->reindexed, 'only labels whose products changed');
    }

    public function testAnEmptySelectionRemovesEveryAssignment(): void
    {
        $this->observer()->execute($this->event(['openlabel_touched' => '1']));

        self::assertSame([[42, []]], $this->saved);
    }

    public function testFormsWithoutTheSectionOrWithoutPermissionChangeNothing(): void
    {
        $this->observer()->execute($this->event(['name' => 'Bag']));
        $this->allowed = false;
        $this->observer()->execute($this->event(['openlabel_touched' => '1', 'openlabel_label_ids' => ['3']]));

        self::assertSame([], $this->saved);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function event(array $post): Observer
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(42);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $key === 'product' ? $post : null);
        $controller = $this->createStub(Action::class);
        $controller->method('getRequest')->willReturn($request);

        return new Observer(['product' => $product, 'controller' => $controller]);
    }

    private function observer(): SaveProductLabels
    {
        $assignments = $this->createStub(ManualAssignments::class);
        $assignments->method('setForProduct')->willReturnCallback(function (int $productId, array $labelIds): array {
            $this->saved[] = [$productId, array_map('intval', $labelIds)];

            return array_values(array_diff(array_map('intval', $labelIds), [3]));
        });
        $reindexer = $this->createStub(LabelReindexer::class);
        $reindexer->method('reindexLabel')->willReturnCallback(function (int $labelId) {
            $this->reindexed[] = $labelId;

            return new \Iranimij\OpenLabel\Model\Indexer\Diff([], []);
        });
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(fn (): bool => $this->allowed);

        return new SaveProductLabels($assignments, $reindexer, $authorization);
    }
}
