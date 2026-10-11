<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterfaceFactory;
use Iranimij\OpenLabel\Model\Condition\Stock;
use Iranimij\OpenLabel\Model\Label\ConditionDiagnosis;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * An empty label explains itself: each condition alone, with its own product count.
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-why-a', 'price' => 10], 'a')]
class ConditionDiagnosisTest extends TestCase
{
    public function testEachConditionOnItsOwnWithItsCount(): void
    {
        $om = Bootstrap::getObjectManager();
        $label = $om->get(LabelInterfaceFactory::class)->create();
        $label->setConditionsSerialized($om->get(Json::class)->serialize([
            'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'ol-why-a'],
                ['type' => Stock::class, 'attribute' => 'is_salable', 'operator' => '==', 'value' => '0'],
                ['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                    ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'ol-why-nothing'],
                ]],
            ],
        ]));

        $rows = $om->get(ConditionDiagnosis::class)->diagnose($label, 1);

        self::assertCount(4, $rows);
        self::assertSame(['text' => 'SKU is ol-why-a', 'count' => 1, 'level' => 0], $rows[0]);
        self::assertStringStartsWith('In stock (salable) is', $rows[1]['text']);
        self::assertSame(0, $rows[1]['level']);
        self::assertSame('If ALL of these conditions are TRUE:', $rows[2]['text']);
        self::assertSame(0, $rows[2]['count']);
        self::assertSame(['text' => 'SKU is ol-why-nothing', 'count' => 0, 'level' => 1], $rows[3]);
    }

    public function testLabelWithoutConditionsHasNothingToDiagnose(): void
    {
        $label = Bootstrap::getObjectManager()->get(LabelInterfaceFactory::class)->create();

        self::assertSame([], Bootstrap::getObjectManager()->get(ConditionDiagnosis::class)->diagnose($label, 1));
    }
}
