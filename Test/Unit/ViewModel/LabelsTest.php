<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\ViewModel;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * "Hide labels on these pages" (1.1): no labels and no query on the chosen page types and layout handles.
 */
class LabelsTest extends TestCase
{
    private string $action = 'catalog_category_view';

    /** @var string[] */
    private array $pageTypes = [];

    private string $handles = '';

    private int $queries = 0;

    public function testLabelsShowEverywhereByDefault(): void
    {
        self::assertTrue($this->labels()->isEnabled());
    }

    public function testAHiddenPageTypeGetsNoLabelsAndNoQuery(): void
    {
        $this->pageTypes = ['category', 'search'];
        $labels = $this->labels();

        self::assertFalse($labels->isEnabled());
        self::assertSame([], $labels->getForProducts([1, 2]));
        self::assertSame(0, $this->queries);

        $this->action = 'catalogsearch_result_index';
        self::assertFalse($this->labels()->isEnabled());
        $this->action = 'catalog_product_view';
        self::assertTrue($this->labels()->isEnabled());
    }

    public function testExtraPagesAreListedByTheirFullActionName(): void
    {
        $this->handles = " cms_index_index\n\nbrand_index_view ";
        $this->action = 'brand_index_view';

        self::assertFalse($this->labels()->isEnabled());
    }

    private function labels(): Labels
    {
        $resolver = $this->createStub(LabelResolverInterface::class);
        $resolver->method('getForProducts')->willReturnCallback(function (): array {
            $this->queries++;

            return [];
        });
        $config = $this->createStub(TypedReader::class);
        $config->method('getBool')->willReturn(true);
        $config->method('getList')->willReturnCallback(fn (): array => $this->pageTypes);
        $config->method('getString')->willReturnCallback(fn (): string => $this->handles);
        $request = $this->createStub(HttpRequest::class);
        $request->method('getFullActionName')->willReturnCallback(fn (): string => $this->action);

        return new Labels(
            $resolver,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(HttpContext::class),
            $config,
            $request
        );
    }
}
