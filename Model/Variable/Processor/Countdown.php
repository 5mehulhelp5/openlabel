<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Variable\Processor;

use Iranimij\OpenLabel\Api\VariableProcessorInterface;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * {COUNTDOWN}: live time left until the special price ends (the end of its last day, like {SPECIAL_ENDS_IN}).
 * The page carries the time left when it was rendered; the theme script (openlabel-hyva) ticks it every second.
 * The ticking part is hidden from screen readers, which read the end date instead.
 */
class Countdown implements VariableProcessorInterface
{
    /**
     * @param DateTime $dateTime
     * @param TimezoneInterface $timezone
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly DateTime $dateTime,
        private readonly TimezoneInterface $timezone,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'COUNTDOWN';
    }

    /**
     * @inheritDoc
     */
    public function getValue(ProductInterface $product, Context $context, string $argument = ''): Value
    {
        $remaining = SpecialEndDate::remainingSeconds($product, $this->dateTime);
        if ($remaining === null) {
            return Value::empty();
        }
        $end = $this->dateTime->gmtTimestamp() + $remaining;
        $lastDay = (new \DateTime('@' . ($end - 1)))->setTimezone(new \DateTimeZone('UTC'));

        return Value::html(sprintf(
            '<span class="ol-countdown" data-ol-end="%s"><span aria-hidden="true">%s</span><span class="ol-sr">%s</span></span>',
            gmdate('Y-m-d\TH:i:s\Z', $end),
            $this->format($remaining),
            $this->escaper->escapeHtml((string) __('Ends %1', $this->timezone->formatDate($lastDay, \IntlDateFormatter::SHORT)))
        ));
    }

    /**
     * "2d 04:12:09", or "04:12:09" under a day; the theme script uses the same format.
     *
     * @param int $seconds
     * @return string
     */
    private function format(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $clock = sprintf('%02d:%02d:%02d', intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60), $seconds % 60);

        return $days > 0 ? $days . 'd ' . $clock : $clock;
    }
}
