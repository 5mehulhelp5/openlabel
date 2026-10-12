<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Ui\DataProvider\Product;

use Iranimij\OpenLabel\Model\Indexer\IndexReader;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Model\ResourceModel\Label\CollectionFactory;
use Iranimij\OpenLabel\Ui\DataProvider\Product\LabelsModifier;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * The "OpenLabel" section of the product edit page (1.1): labels the product shows now, and labels it always shows.
 */
class LabelsModifierTest extends TestCase
{
    private bool $allowed = true;

    public function testTheSectionListsCurrentLabelsAndOffersEveryLabelToAssign(): void
    {
        $meta = $this->modifier()->modifyMeta([]);

        $section = $meta['openlabel'];
        self::assertSame('OpenLabel', (string) $section['arguments']['data']['config']['label']);
        $applied = (string) $section['children']['openlabel_applied']['arguments']['data']['config']['content'];
        self::assertStringContainsString('<a href="https://admin.test/openlabel/label/edit/id/3">Sale &amp; more</a>', $applied);
        self::assertStringContainsString('(by hand)', $applied);
        $field = $section['children']['openlabel_label_ids']['arguments']['data']['config'];
        self::assertSame('multiselect', $field['formElement']);
        self::assertSame([['value' => '3', 'label' => 'Sale & more'], ['value' => '5', 'label' => 'New (off)']], $field['options']);
    }

    public function testTheProductCarriesItsAssignedLabels(): void
    {
        $data = $this->modifier()->modifyData([42 => ['product' => ['sku' => 'x']]]);

        self::assertSame(['3'], $data[42]['product']['openlabel_label_ids']);
        self::assertSame('1', $data[42]['product']['openlabel_touched'], 'tells the save that the section was on the form');
    }

    public function testWithoutTheLabelsPermissionThereIsNoSection(): void
    {
        $this->allowed = false;

        self::assertSame([], $this->modifier()->modifyMeta([]));
        self::assertSame([42 => ['product' => []]], $this->modifier()->modifyData([42 => ['product' => []]]));
    }

    public function testANewProductShowsNoCurrentLabels(): void
    {
        $meta = $this->modifier(0)->modifyMeta([]);

        self::assertStringContainsString(
            'after the product is saved',
            (string) $meta['openlabel']['children']['openlabel_applied']['arguments']['data']['config']['content']
        );
    }

    private function modifier(int $productId = 42): LabelsModifier
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId ?: null);
        $locator = $this->createStub(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($product);
        $assignments = $this->createStub(ManualAssignments::class);
        $assignments->method('forProduct')->willReturn([3]);
        $reader = $this->createStub(IndexReader::class);
        $reader->method('labelsOfProduct')->willReturn([3 => 'Sale & more']);
        $collection = $this->createStub(\Iranimij\OpenLabel\Model\ResourceModel\Label\Collection::class);
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getData')->willReturn([
            ['label_id' => '3', 'name' => 'Sale & more', 'status' => '1'],
            ['label_id' => '5', 'name' => 'New', 'status' => '0'],
        ]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(fn (): bool => $this->allowed);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://admin.test/' . $route . '/id/' . ($params['id'] ?? '')
        );

        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($text): string => htmlspecialchars((string) $text));
        $escaper->method('escapeUrl')->willReturnCallback(static fn ($url): string => htmlspecialchars((string) $url));

        return new LabelsModifier($locator, $assignments, $reader, $collections, $authorization, $url, $escaper);
    }
}
