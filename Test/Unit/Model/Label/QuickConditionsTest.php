<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Label;

use Iranimij\OpenLabel\Model\Condition\Bestseller;
use Iranimij\OpenLabel\Model\Condition\IsNew;
use Iranimij\OpenLabel\Model\Condition\OnSale;
use Iranimij\OpenLabel\Model\Condition\Rating;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Label\QuickConditions;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class QuickConditionsTest extends TestCase
{
    private function quick(): QuickConditions
    {
        return new QuickConditions(new Json());
    }

    /**
     * @return array<string, mixed>
     */
    private function advanced(): array
    {
        return ['type' => Combine::class, 'aggregator' => 'any', 'value' => '1', 'conditions' => [
            ['type' => 'Iranimij\OpenLabel\Model\Rule\Condition\Product', 'attribute' => 'color', 'operator' => '==', 'value' => '49'],
        ]];
    }

    public function testNothingChosenMeansNoConditions(): void
    {
        self::assertNull($this->quick()->compose([], null));
        self::assertNull($this->quick()->compose(['on_sale' => '0', 'on_sale_min' => '20'], ['type' => Combine::class, 'conditions' => []]));
    }

    public function testEachToggleBecomesItsBuiltInCondition(): void
    {
        $json = $this->quick()->compose([
            'on_sale' => '1', 'on_sale_min' => '20',
            'is_new' => '1', 'new_days' => '30',
            'low_stock' => '1', 'low_stock_qty' => '5',
            'out_of_stock' => '1',
            'rating' => '1', 'rating_min' => '4',
            'bestseller' => '1', 'bestseller_top' => '10',
        ], null);

        $tree = json_decode((string) $json, true);
        self::assertSame(Combine::class, $tree['type']);
        self::assertSame('all', $tree['aggregator']);
        $leaves = array_map(static fn (array $c): array => [$c['type'], $c['attribute'], $c['operator'], $c['value']], $tree['conditions']);
        self::assertSame([
            [OnSale::class, 'discount_percent', '>=', '20'],
            [IsNew::class, 'days_since_created', '<=', '30'],
            [Stock::class, 'salable_qty', '<=', '5'],
            [Stock::class, 'is_salable', '==', '0'],
            [Rating::class, 'rating', '>=', '4'],
            [Bestseller::class, 'sales_rank_30d', '<=', '10'],
        ], $leaves);
    }

    public function testOnSaleWithoutMinimumMeansAnyDiscount(): void
    {
        $tree = json_decode((string) $this->quick()->compose(['on_sale' => '1', 'on_sale_min' => ''], null), true);

        self::assertSame([OnSale::class, 'on_sale', '==', '1'], [
            $tree['conditions'][0]['type'], $tree['conditions'][0]['attribute'],
            $tree['conditions'][0]['operator'], $tree['conditions'][0]['value'],
        ]);
    }

    public function testTogglesAndAdvancedTreeAreCombinedWithAnd(): void
    {
        $tree = json_decode((string) $this->quick()->compose(['on_sale' => '1', 'on_sale_min' => '10'], $this->advanced()), true);

        self::assertSame('all', $tree['aggregator']);
        self::assertCount(2, $tree['conditions']);
        self::assertSame('any', $tree['conditions'][1]['aggregator'], 'the advanced tree keeps its own ALL/ANY');
        self::assertSame('color', $tree['conditions'][1]['conditions'][0]['attribute']);
    }

    public function testRoundTrip(): void
    {
        $quick = ['on_sale' => '1', 'on_sale_min' => '15', 'low_stock' => '1', 'low_stock_qty' => '3', 'bestseller' => '1', 'bestseller_top' => '5'];
        $json = (string) $this->quick()->compose($quick, $this->advanced());

        [$values, $advanced] = $this->quick()->decompose($json);

        self::assertSame('1', $values['on_sale']);
        self::assertSame('15', $values['on_sale_min']);
        self::assertSame('1', $values['low_stock']);
        self::assertSame('3', $values['low_stock_qty']);
        self::assertSame('0', $values['is_new']);
        self::assertSame('0', $values['out_of_stock']);
        self::assertSame(['1', '5'], [$values['bestseller'], $values['bestseller_top']]);
        self::assertSame($this->advanced()['conditions'], $advanced['conditions']);
        self::assertSame($json, $this->quick()->compose($values, $advanced), 'saving again changes nothing');
    }

    public function testAForeignTreeIsShownAsAdvancedOnly(): void
    {
        $legacy = (string) json_encode(['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
            ['type' => OnSale::class, 'attribute' => 'on_sale', 'operator' => '==', 'value' => '1'],
        ]]);

        [$values, $advanced] = $this->quick()->decompose($legacy);

        self::assertSame('0', $values['on_sale']);
        self::assertSame(OnSale::class, $advanced['conditions'][0]['type']);
    }

    public function testEmptyStoredConditions(): void
    {
        [$values, $advanced] = $this->quick()->decompose(null);

        self::assertSame('0', $values['rating']);
        self::assertSame(['0', '10'], [$values['bestseller'], $values['bestseller_top']], 'top 10 offered when switched on');
        self::assertNull($advanced);
    }
}
