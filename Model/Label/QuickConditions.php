<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Model\Condition\Bestseller;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Condition\Rating;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The "Show when" quick toggles (08 · UX Spec §4) and the advanced rule tree in one stored condition tree:
 * an ALL root holding one built-in condition per toggle plus the merchant's advanced tree as a nested
 * combination, so toggles and tree combine with AND. Toggle leaves carry an "ol_quick" key so the form can show
 * them as toggles again; a tree saved elsewhere (CLI, import) is shown as advanced conditions only.
 */
class QuickConditions
{
    private const LAYOUT = 'ol_layout';
    private const QUICK = 'ol_quick';
    private const ADVANCED = 'ol_advanced';

    /**
     * Toggle => [value field, condition class, attribute, operator, fixed value when no value field].
     */
    private const TOGGLES = [
        'on_sale' => ['on_sale_min', OnSale::class, OnSale::DISCOUNT_PERCENT, '>=', null],
        'is_new' => ['new_days', IsNew::class, IsNew::DAYS_SINCE_CREATED, '<=', null],
        'low_stock' => ['low_stock_qty', Stock::class, Stock::SALABLE_QTY, '<=', null],
        'out_of_stock' => [null, Stock::class, Stock::IS_SALABLE, '==', '0'],
        'rating' => ['rating_min', Rating::class, Rating::RATING, '>=', null],
        'bestseller' => ['bestseller_top', Bestseller::class, Bestseller::RANK_30D, '<=', null],
    ];

    /** Defaults shown when a toggle is switched on for the first time. */
    public const DEFAULT_VALUES = ['on_sale_min' => '10', 'new_days' => '30', 'low_stock_qty' => '5', 'rating_min' => '4', 'bestseller_top' => '10'];

    /**
     * @param Json $json
     */
    public function __construct(
        private readonly Json $json
    ) {
    }

    /**
     * @param array<string, mixed> $values toggle flags and their numbers from the form
     * @param array<string, mixed>|null $advanced the advanced tree (a combine condition as an array)
     * @return string|null serialized conditions, null when nothing restricts the label
     */
    public function compose(array $values, ?array $advanced): ?string
    {
        $conditions = [];
        foreach (self::TOGGLES as $toggle => [$field, $class, $attribute, $operator, $fixed]) {
            if (!$this->on($values[$toggle] ?? null)) {
                continue;
            }
            $value = $fixed ?? trim((string) ($values[$field] ?? ''));
            if ($toggle === 'on_sale' && $value === '') {
                [$attribute, $operator, $value] = [OnSale::ON_SALE, '==', '1'];
            }
            if ($value === '') {
                $value = self::DEFAULT_VALUES[$field] ?? '0';
            }
            $conditions[] = [
                'type' => $class,
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value,
                self::QUICK => $toggle,
            ];
        }
        if ($advanced !== null && ($advanced['conditions'] ?? []) !== []) {
            unset($advanced[self::ADVANCED]);
            $advanced[self::ADVANCED] = '1';
            $conditions[] = $advanced;
        }
        if ($conditions === []) {
            return null;
        }

        return $this->json->serialize([
            'type' => Combine::class,
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => $conditions,
            self::LAYOUT => 'quick',
        ]);
    }

    /**
     * @param string|null $serialized
     * @return array{0: array<string, string>, 1: array<string, mixed>|null} toggle values, advanced tree
     */
    public function decompose(?string $serialized): array
    {
        $values = [];
        foreach (self::TOGGLES as $toggle => [$field]) {
            $values[$toggle] = '0';
            if ($field !== null) {
                $values[$field] = self::DEFAULT_VALUES[$field];
            }
        }
        $tree = $serialized === null || trim($serialized) === '' ? null : $this->json->unserialize($serialized);
        if (!is_array($tree)) {
            return [$values, null];
        }
        if (($tree[self::LAYOUT] ?? null) !== 'quick') {
            return [$values, $tree];
        }
        $advanced = null;
        foreach ($tree['conditions'] ?? [] as $condition) {
            $toggle = $condition[self::QUICK] ?? null;
            if (is_string($toggle) && isset(self::TOGGLES[$toggle])) {
                $values[$toggle] = '1';
                $field = self::TOGGLES[$toggle][0];
                if ($field !== null) {
                    $values[$field] = $condition['attribute'] === OnSale::ON_SALE ? '' : (string) $condition['value'];
                }
            } elseif (isset($condition[self::ADVANCED])) {
                unset($condition[self::ADVANCED]);
                $advanced = $condition;
            }
        }

        return [$values, $advanced];
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function on(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }
}
