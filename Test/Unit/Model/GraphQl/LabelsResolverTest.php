<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\GraphQl;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\GraphQl\ContextReader;
use Iranimij\OpenLabel\Model\GraphQl\LabelData;
use Iranimij\OpenLabel\Model\GraphQl\LabelsResolver;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchRequestItemInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use PHPUnit\Framework\TestCase;

class LabelsResolverTest extends TestCase
{
    /** @var array<int, array{int[], int, int}> calls to the label resolver: ids, store, group */
    private array $calls = [];

    /** @var string[] texts handed to the variable preloader */
    private array $preloaded = [];

    private bool $enabled = true;

    /** label id the stubbed LabelData leaves out (hide on zero) */
    private int $hiddenLabel = 0;

    public function testOneLookupForAllProductsOfTheQuery(): void
    {
        $requests = [$this->request(100), $this->request(200), $this->request(300)];

        $response = $this->resolver()->resolve($this->context(2, 3), $this->field(), $requests);

        self::assertSame([[[100, 200, 300], 2, 3]], $this->calls);
        self::assertSame([['id' => 1, 'product' => 100], ['id' => 2, 'product' => 100]], $response->findResponseFor($requests[0]));
        self::assertSame([['id' => 1, 'product' => 200], ['id' => 2, 'product' => 200]], $response->findResponseFor($requests[1]));
        self::assertSame([], $response->findResponseFor($requests[2]), 'a product without labels gets an empty list');
    }

    public function testVariablesArePreloadedOnceWithTheTextsInUse(): void
    {
        $this->resolver()->resolve($this->context(1, 0), $this->field(), [$this->request(100), $this->request(200)]);

        self::assertSame(['-{SAVE_PERCENT}%'], $this->preloaded);
    }

    public function testLabelsHiddenByTheirVariablesAreLeftOut(): void
    {
        $this->hiddenLabel = 1;
        $request = $this->request(100);

        $response = $this->resolver()->resolve($this->context(1, 0), $this->field(), [$request]);

        self::assertSame([['id' => 2, 'product' => 100]], $response->findResponseFor($request));
    }

    public function testDisabledModuleAnswersEmptyListsWithoutAQuery(): void
    {
        $this->enabled = false;
        $request = $this->request(100);

        $response = $this->resolver()->resolve($this->context(1, 0), $this->field(), [$request]);

        self::assertSame([], $this->calls);
        self::assertSame([], $response->findResponseFor($request));
    }

    private function resolver(): LabelsResolver
    {
        $resolver = $this->createStub(LabelResolverInterface::class);
        $resolver->method('getForProducts')->willReturnCallback(
            function (array $ids, int $store, int $group): array {
                $this->calls[] = [$ids, $store, $group];
                $labels = [];
                foreach (array_intersect($ids, [100, 200]) as $id) {
                    $labels[$id] = [$this->resolved(1, $id, '-{SAVE_PERCENT}%'), $this->resolved(2, $id, 'New')];
                }

                return $labels;
            }
        );
        $labelData = $this->createStub(LabelData::class);
        $labelData->method('toArray')->willReturnCallback(
            fn (ResolvedLabelInterface $label, Product $product): ?array => $label->getLabelId() === $this->hiddenLabel ? null : [
                'id' => $label->getLabelId(),
                'product' => (int) $product->getId(),
            ]
        );
        $renderer = $this->createStub(Renderer::class);
        $renderer->method('preloadFor')->willReturnCallback(function (array $products, Context $context, array $texts): void {
            $this->preloaded = array_merge($this->preloaded, $texts);
        });
        $config = $this->createStub(TypedReader::class);
        $config->method('getBool')->willReturnCallback(fn (): bool => $this->enabled);

        $contextReader = $this->createStub(ContextReader::class);
        $contextReader->method('read')->willReturnCallback(
            fn (): Context => new Context($this->storeId, 1, $this->groupId ?? 0)
        );

        return new LabelsResolver($resolver, $labelData, $renderer, $config, $contextReader);
    }

    private function resolved(int $id, int $productId, string $text): ResolvedLabelInterface
    {
        $label = $this->createStub(ResolvedLabelInterface::class);
        $label->method('getLabelId')->willReturn($id);
        $label->method('getProductId')->willReturn($productId);
        $label->method('getText')->willReturn($text);

        return $label;
    }

    private function request(int $productId): BatchRequestItemInterface
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);
        $request = $this->createStub(BatchRequestItemInterface::class);
        $request->method('getValue')->willReturn(['model' => $product]);

        return $request;
    }

    private function context(int $storeId, ?int $groupId): ContextInterface
    {
        $this->storeId = $storeId;
        $this->groupId = $groupId;

        return $this->createStub(ContextInterface::class);
    }

    private int $storeId = 1;

    private ?int $groupId = null;

    private function field(): Field
    {
        return $this->createStub(Field::class);
    }
}
