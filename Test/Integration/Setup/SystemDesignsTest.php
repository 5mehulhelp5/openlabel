<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Setup;

use Iranimij\OpenLabel\Model\Design\SystemDesignCatalog;
use Iranimij\OpenLabel\Setup\Patch\Data\SystemDesigns;
use Iranimij\OpenLabel\Setup\Patch\Data\SystemDesignsOneDotOne;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class SystemDesignsTest extends TestCase
{
    public function testInstallCreatesTheThirtyOneSystemDesignsWithDefaultText(): void
    {
        $rows = $this->systemRows();

        self::assertCount(31, $rows);
        $names = array_column((new SystemDesignCatalog())->getDefinitions(), 'name');
        self::assertEqualsCanonicalizing($names, array_column($rows, 'name'));
        self::assertNotContains(null, array_column($rows, 'text'), 'every system design has store-0 text');
    }

    public function testApplyingAgainCreatesNoDuplicates(): void
    {
        Bootstrap::getObjectManager()->create(SystemDesigns::class)->apply();
        Bootstrap::getObjectManager()->create(SystemDesignsOneDotOne::class)->apply();

        self::assertCount(31, $this->systemRows());
    }

    public function testApplyingAgainRestoresAMissingDesignButKeepsMerchantEdits(): void
    {
        $connection = $this->connection();
        $table = $connection->getTableName('openlabel_design');
        $connection->update($table, ['bg_color' => '#000000'], ['name = ?' => 'Sale pill (red)', 'is_system = ?' => 1]);
        $connection->delete($table, ['name = ?' => 'Eco (forest)', 'is_system = ?' => 1]);

        Bootstrap::getObjectManager()->create(SystemDesigns::class)->apply();

        $byName = array_column($this->systemRows(), null, 'name');
        self::assertCount(31, $byName);
        self::assertSame('#000000', $byName['Sale pill (red)']['bg_color'], 'merchant edit kept');
        self::assertSame('#166534', $byName['Eco (forest)']['bg_color'], 'missing design restored');
    }

    public function testTheOneDotOnePatchRunsAfterTheOneDotZeroPatch(): void
    {
        self::assertSame([SystemDesigns::class], SystemDesignsOneDotOne::getDependencies());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function systemRows(): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['d' => $connection->getTableName('openlabel_design')], ['design_id', 'name', 'bg_color'])
            ->joinLeft(
                ['s' => $connection->getTableName('openlabel_design_store')],
                's.design_id = d.design_id AND s.store_id = 0',
                ['text']
            )
            ->where('d.is_system = ?', 1);

        return $connection->fetchAll($select);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }
}
