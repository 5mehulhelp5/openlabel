<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Variable;

use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Processor\Countdown;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * {COUNTDOWN}: time left until the special price ends; $now is 2026-10-09 12:00:00 UTC.
 */
class CountdownTest extends TestCase
{
    private const NOW = '2026-10-09 12:00:00';

    public function testCountdownToTheEndOfTheSpecialPriceDay(): void
    {
        $value = $this->value('2026-10-11 00:00:00');

        self::assertSame(Value::HTML, $value->type);
        self::assertSame(
            '<span class="ol-countdown" data-ol-end="2026-10-12T00:00:00Z"><span aria-hidden="true">2d 12:00:00</span>'
            . '<span class="ol-sr">Ends 11/10/2026</span></span>',
            $value->raw
        );
    }

    public function testUnderADayShowsHoursMinutesSeconds(): void
    {
        self::assertStringContainsString('<span aria-hidden="true">12:00:00</span>', (string) $this->value('2026-10-09 00:00:00')->raw);
    }

    public function testNoEndOrAPastEndIsEmpty(): void
    {
        self::assertTrue($this->value(null)->isEmpty());
        self::assertTrue($this->value('2026-10-01 00:00:00')->isEmpty());
    }

    public function testTheCodeIsCountdown(): void
    {
        self::assertSame('COUNTDOWN', $this->processor()->getCode());
    }

    private function value(?string $specialToDate): Value
    {
        /** @var Product $product */
        $product = (new ObjectManager($this))->getObject(Product::class);
        $product->setData(['special_to_date' => $specialToDate, 'special_price' => 5.0]);

        return $this->processor()->getValue($product, new Context(1, 1, 0));
    }

    private function processor(): Countdown
    {
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(strtotime(self::NOW . ' UTC'));
        $dateTime->method('timestamp')->willReturnCallback(static fn ($v) => strtotime((string) $v . ' UTC'));
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDate')->willReturnCallback(
            static fn ($date): string => $date instanceof \DateTimeInterface ? $date->format('d/m/Y') : (string) $date
        );
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($text): string => htmlspecialchars((string) $text));

        return new Countdown($dateTime, $timezone, $escaper);
    }
}
