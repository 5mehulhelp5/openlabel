<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Variable;

use Iranimij\OpenLabel\Api\VariableProcessorInterface;
use Iranimij\OpenLabel\Model\Html\AllowList;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Pool;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase
{
    public function testSubstitutesAndFormatsNumbersCurrencyAndDatesForTheLocale(): void
    {
        $rendered = $this->renderer('de_DE')->render('Spare {PCT}% ({AMOUNT}) bis {DATE} · {QTY}', $this->product(), $this->context());

        self::assertSame('Spare 21% (€21,00) bis 24.12.2026 · 1.234', $rendered->getHtml());
        self::assertFalse($rendered->hasEmptyVariable());
    }

    public function testValuesAreEscapedAndTheTextKeepsOnlyAllowedHtml(): void
    {
        $text = '<b>Nur</b> {TEXT}{BR}<script>alert(1)</script><span onclick="x">x</span><img src="a">';

        $rendered = $this->renderer('en_US')->render($text, $this->product(), $this->context());

        $html = $rendered->getHtml();
        self::assertStringContainsString('<b>Nur</b> &lt;i&gt;Tom &amp; Jerry&lt;/i&gt;<br>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<span>x</span>', $html);
    }

    public function testMarkupFromVariablesKeepsItsAttributesWhileTheMerchantsTextIsCleaned(): void
    {
        $rendered = $this->renderer('en_US')->render('<span data-x="1" aria-hidden="true">Ends in</span> {TICK}', $this->product(), $this->context());

        self::assertSame(
            '<span>Ends in</span> <span class="ol-countdown" data-ol-end="2026-10-12T00:00:00Z"><span aria-hidden="true">1d</span></span>',
            $rendered->getHtml(),
            'the countdown markup comes from code; attributes typed into the text are still removed'
        );
    }

    public function testZeroVariablesRenderAsZeroAndAreReportedAsEmpty(): void
    {
        $rendered = $this->renderer('en_US')->render('Only {EMPTY} left', $this->product(), $this->context());

        self::assertSame('Only 0 left', $rendered->getHtml(), 'hiding is the hide-on-zero flag\'s job (1.1), the text stays honest');
        self::assertTrue($rendered->hasEmptyVariable());
        self::assertSame(['EMPTY'], $rendered->getEmptyVariables());
    }

    public function testUnknownVariablesStayLiteralSoMistakesAreVisible(): void
    {
        $rendered = $this->renderer('en_US')->render('{NOPE} {PCT}', $this->product(), $this->context());

        self::assertSame('{NOPE} 21', $rendered->getHtml());
        self::assertFalse($rendered->hasEmptyVariable());
    }

    public function testArgumentsReachTheProcessor(): void
    {
        $rendered = $this->renderer('en_US')->render('{ATTR:color} / {ATTR:size}', $this->product(), $this->context());

        self::assertSame('Red / ', $rendered->getHtml());
        self::assertSame(['ATTR:size'], $rendered->getEmptyVariables());
    }

    public function testPreloadForOnlyLoadsTheProcessorsTheTextsUse(): void
    {
        $used = $this->createMock(PreloadingProcessorInterface::class);
        $used->method('getCode')->willReturn('RATING');
        $used->expects(self::once())->method('preload');
        $unused = $this->createMock(PreloadingProcessorInterface::class);
        $unused->method('getCode')->willReturn('SOLD');
        $unused->expects(self::never())->method('preload');
        $renderer = new Renderer(
            new Pool(['RATING' => $used, 'SOLD' => $unused, 'PCT' => $this->processor('PCT', static fn () => Value::number(1))]),
            new AllowList(new Escaper()),
            new Escaper(),
            $this->createStub(PriceCurrencyInterface::class),
            $this->createStub(TimezoneInterface::class),
            $this->createStub(ResolverInterface::class)
        );

        $renderer->preloadFor(
            [$this->createStub(ProductInterface::class)],
            new Context(1, 1, 0),
            ['{RATING} stars', 'Top {RATING}', '-{PCT}%', '{UNKNOWN}']
        );
    }

    private function renderer(string $locale): Renderer
    {
        $pool = new Pool([
            'PCT' => $this->processor('PCT', static fn () => Value::number(21)),
            'AMOUNT' => $this->processor('AMOUNT', static fn () => Value::currency(21.0)),
            'DATE' => $this->processor('DATE', static fn () => Value::date('2026-12-24 00:00:00')),
            'QTY' => $this->processor('QTY', static fn () => Value::number(1234.0)),
            'TEXT' => $this->processor('TEXT', static fn () => Value::text('<i>Tom & Jerry</i>')),
            'BR' => $this->processor('BR', static fn () => Value::html('<br>')),
            'TICK' => $this->processor('TICK', static fn () => Value::html('<span class="ol-countdown" data-ol-end="2026-10-12T00:00:00Z"><span aria-hidden="true">1d</span></span>')),
            'EMPTY' => $this->processor('EMPTY', static fn () => Value::number(0)),
            'ATTR' => $this->processor('ATTR', static fn (ProductInterface $p, Context $c, string $arg) => Value::text($arg === 'color' ? 'Red' : null)),
        ]);
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn ($amount) => $locale === 'de_DE' ? '€' . number_format((float) $amount, 2, ',', '.') : '$' . number_format((float) $amount, 2)
        );
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDate')->willReturn($locale === 'de_DE' ? '24.12.2026' : '12/24/26');
        $localeResolver = $this->createStub(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn($locale);

        return new Renderer($pool, new AllowList(new Escaper()), new Escaper(), $priceCurrency, $timezone, $localeResolver);
    }

    private function processor(string $code, callable $value): VariableProcessorInterface
    {
        $processor = $this->createStub(VariableProcessorInterface::class);
        $processor->method('getCode')->willReturn($code);
        $processor->method('getValue')->willReturnCallback($value);

        return $processor;
    }

    private function product(): Product
    {
        /** @var Product $product */
        $product = (new ObjectManager($this))->getObject(Product::class);
        $product->setData(['sku' => 'X']);

        return $product;
    }

    private function context(): Context
    {
        return new Context(1, 1, 0);
    }
}
