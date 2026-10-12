<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Variable;

use Iranimij\OpenLabel\Api\VariablePreloadInterface;
use Iranimij\OpenLabel\Model\Html\AllowList;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Substitutes {VARIABLES} in a label text with locale-formatted, escaped values and applies the HTML allow-list.
 * Unknown variables stay literal so a typo is visible in the admin preview instead of silently blank.
 */
class Renderer
{
    private const PATTERN = '/\{([A-Z][A-Z0-9_]*)(?::([A-Za-z0-9_]+))?\}/';

    /** Marks where variable markup goes back after cleaning (a private-use character, never typed or escaped). */
    private const MARKUP_TOKEN = "\u{E000}";

    /**
     * @param Pool $pool
     * @param AllowList $allowList
     * @param Escaper $escaper
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        private readonly Pool $pool,
        private readonly AllowList $allowList,
        private readonly Escaper $escaper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        private readonly LocaleResolver $localeResolver
    ) {
    }

    /**
     * Load everything the variables need for a whole listing in one go (one query per processor, not per product).
     *
     * @param ProductInterface[] $products
     * @param Context $context
     * @return void
     */
    public function preload(array $products, Context $context): void
    {
        foreach ($this->pool->getAll() as $processor) {
            if ($processor instanceof VariablePreloadInterface) {
                $processor->preload($products, $context);
            }
        }
    }

    /**
     * Like preload(), limited to the processors the given texts use: a listing whose labels only show
     * {SAVE_PERCENT} costs no review or sales query.
     *
     * @param ProductInterface[] $products
     * @param Context $context
     * @param string[] $texts
     * @return void
     */
    public function preloadFor(array $products, Context $context, array $texts): void
    {
        $done = [];
        foreach ($texts as $text) {
            preg_match_all(self::PATTERN, $text, $matches);
            foreach ($matches[1] as $name) {
                $processor = $this->pool->get($name);
                if ($processor instanceof VariablePreloadInterface && !isset($done[spl_object_id($processor)])) {
                    $done[spl_object_id($processor)] = true;
                    $processor->preload($products, $context);
                }
            }
        }
    }

    /**
     * @param string $text label text with {VARIABLES} and allow-listed inline HTML
     * @param ProductInterface $product
     * @param Context $context
     * @return Rendered
     */
    public function render(string $text, ProductInterface $product, Context $context): Rendered
    {
        $empty = [];
        // Markup from variables is produced by code ({BR}, {COUNTDOWN}): it stays out of the allow-list, which is
        // there for the merchant's text, and goes back in after cleaning.
        $markup = [];
        $substituted = (string) preg_replace_callback(
            self::PATTERN,
            function (array $match) use ($product, $context, &$empty, &$markup): string {
                $processor = $this->pool->get($match[1]);
                if ($processor === null) {
                    return $match[0];
                }
                $argument = $match[2] ?? '';
                $value = $processor->getValue($product, $context, $argument);
                if ($value->isEmpty()) {
                    $empty[] = $match[1] . ($argument !== '' ? ':' . $argument : '');
                }
                if ($value->type === Value::HTML && !$value->isEmpty()) {
                    $token = self::MARKUP_TOKEN . count($markup) . self::MARKUP_TOKEN;
                    $markup[$token] = (string) $value->raw;

                    return $token;
                }

                return $this->formatForHtml($value, $context);
            },
            $text
        );

        return new Rendered(strtr($this->allowList->clean($substituted), $markup), $empty);
    }

    /**
     * Locale formatting without escaping (admin preview, plain-text contexts).
     *
     * @param Value $value
     * @param Context $context
     * @return string
     */
    public function format(Value $value, Context $context): string
    {
        if ($value->isEmpty() && $value->type !== Value::NUMBER && $value->type !== Value::CURRENCY) {
            return '';
        }
        switch ($value->type) {
            case Value::CURRENCY:
                return (string) $this->priceCurrency->format((float) $value->raw, false, $value->precision, $context->storeId);
            case Value::NUMBER:
                $formatter = new \NumberFormatter($this->localeResolver->getLocale(), \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $value->precision);
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $value->precision);

                return (string) $formatter->format((float) $value->raw);
            case Value::DATE:
                return (string) $this->timezone->formatDate(
                    new \DateTime((string) $value->raw, new \DateTimeZone('UTC')),
                    \IntlDateFormatter::SHORT
                );
            default:
                return (string) $value->raw;
        }
    }

    /**
     * @param Value $value
     * @param Context $context
     * @return string
     */
    private function formatForHtml(Value $value, Context $context): string
    {
        if ($value->type === Value::HTML) {
            return (string) $value->raw;
        }

        return (string) $this->escaper->escapeHtml($this->format($value, $context));
    }
}
