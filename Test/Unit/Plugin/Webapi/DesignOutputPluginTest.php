<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Plugin\Webapi;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignSearchResultsInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Plugin\Webapi\DesignOutputPlugin;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Magento 2.4.7 and 2.4.8 output an `UnstructuredArray` field element by element, cast and without its keys (2.4.9
 * passes it through); the plugin puts a design's store texts and sizes back as they are.
 */
class DesignOutputPluginTest extends TestCase
{
    private const TEXTS = [0 => ['text' => 'Sale', 'alt_text' => null, 'tooltip' => 'Ends Sunday'], 2 => ['text' => 'Deal', 'alt_text' => null, 'tooltip' => null]];
    private const SIZES = ['sm' => ['width' => 40]];

    public function testOneDesignGetsItsStoreTextsAndSizesBack(): void
    {
        $broken = ['design_id' => 5, 'store_texts' => ['Array', 'Array'], 'sizes' => ['Array']];

        $result = $this->plugin()->afterProcess($this->processor(), $broken, $this->design(), 'X', 'getById');

        self::assertSame(self::TEXTS, $result['store_texts']);
        self::assertSame(self::SIZES, $result['sizes']);
        self::assertSame(5, $result['design_id']);
    }

    public function testEveryDesignOfASearchResultGetsThemBack(): void
    {
        $results = $this->createStub(DesignSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$this->design(), $this->design()]);
        $broken = ['items' => [['store_texts' => ['Array']], ['store_texts' => ['Array'], 'sizes' => []]], 'total_count' => 2];

        $result = $this->plugin()->afterProcess($this->processor(), $broken, $results, 'X', 'getList');

        self::assertSame(self::TEXTS, $result['items'][0]['store_texts']);
        self::assertSame(self::SIZES, $result['items'][1]['sizes']);
        self::assertSame(2, $result['total_count']);
    }

    public function testOtherDataIsLeftAlone(): void
    {
        $output = ['label_id' => 3, 'store_ids' => [1, 2]];

        self::assertSame($output, $this->plugin()->afterProcess($this->processor(), $output, $this->createStub(LabelInterface::class), 'X', 'getById'));
        self::assertSame(true, $this->plugin()->afterProcess($this->processor(), true, true, 'X', 'deleteById'));
    }

    private function design(): DesignInterface
    {
        $design = $this->createStub(DesignInterface::class);
        $design->method('getStoreTexts')->willReturn(self::TEXTS);
        $design->method('getSizes')->willReturn(self::SIZES);

        return $design;
    }

    private function processor(): ServiceOutputProcessor
    {
        return $this->createStub(ServiceOutputProcessor::class);
    }

    private function plugin(): DesignOutputPlugin
    {
        return new DesignOutputPlugin();
    }
}
