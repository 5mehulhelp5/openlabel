<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Resolver;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\Design\SystemDesignCatalog;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\TranslateInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Label packs (1.1): built-in designs speak the store view's language; a merchant's own design keeps its text.
 */
#[AppArea('frontend')]
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-pack'], 'product')]
#[DataFixture(DesignFixture::class, ['name' => 'Own new', 'store_texts' => [0 => ['text' => 'New']]], 'own')]
class LabelPackTest extends TestCase
{
    /** @var int[] */
    private ?array $labelIds = [];

    protected function tearDown(): void
    {
        $om = Bootstrap::getObjectManager();
        foreach ($this->labelIds as $labelId) {
            $om->get(LabelRepositoryInterface::class)->deleteById($labelId);
        }
        $om->get(TranslateInterface::class)->setLocale('en_US')->loadData(Area::AREA_FRONTEND, true);
    }

    public function testBuiltInDesignsUseTheStoreViewsLanguage(): void
    {
        $om = Bootstrap::getObjectManager();
        $storage = DataFixtureStorageManager::getStorage();
        $builtIn = $this->builtInDesignId((string) (new SystemDesignCatalog())->getDefinitions()[SystemDesignCatalog::NEW]['name']);
        $ownLabel = $this->label('Own', (int) $storage->get('own')->getDesignId());
        $builtInLabel = $this->label('Built in', $builtIn);
        $productId = (int) $storage->get('product')->getId();

        $om->get(TranslateInterface::class)->setLocale('fr_FR')->loadData(Area::AREA_FRONTEND, true);
        $texts = [];
        foreach ($om->get(LabelResolverInterface::class)->getForProducts([$productId], 1, 0)[$productId] ?? [] as $label) {
            $texts[$label->getLabelId()] = $label->getText();
        }

        self::assertSame('Nouveau', $texts[$builtInLabel] ?? null, 'the French label pack');
        self::assertSame('New', $texts[$ownLabel] ?? null, 'the merchant wrote this text: not translated');
    }

    /**
     * @param string $name
     * @param int $designId
     * @return int label ID
     */
    private function label(string $name, int $designId): int
    {
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply([
            'name' => $name,
            'design_id' => $designId,
            'conditions_serialized' => json_encode([
                'type' => Combine::class, 'aggregator' => 'all', 'value' => '1',
                'conditions' => [['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '==', 'value' => 'ol-pack']],
            ]),
            'placements' => [['area' => 'listing', 'position' => $name === 'Own' ? 'tl' : 'tr']],
        ]);
        $this->labelIds[] = (int) $label->getLabelId();

        return (int) $label->getLabelId();
    }

    /**
     * @param string $name
     * @return int
     */
    private function builtInDesignId(string $name): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($resource->getTableName('openlabel_design'), ['design_id'])
                ->where('name = ?', $name)->where('is_system = 1')
        );
    }
}
