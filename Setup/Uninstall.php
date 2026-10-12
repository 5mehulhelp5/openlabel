<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * Removes every OpenLabel table and configuration value on `module:uninstall -r`.
 */
class Uninstall implements UninstallInterface
{
    /** Child tables first so foreign keys never block a drop. */
    private const TABLES = [
        'openlabel_index_replica',
        'openlabel_index',
        'openlabel_placement',
        'openlabel_label_product',
        'openlabel_design_store',
        'openlabel_label',
        'openlabel_design',
    ];

    /**
     * @inheritDoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $connection = $setup->getConnection();
        foreach (self::TABLES as $table) {
            $name = $setup->getTable($table);
            if ($connection->isTableExists($name)) {
                $connection->dropTable($name);
            }
        }
        foreach (['openlabel/%', 'openlabel_css/%'] as $path) {
            $connection->delete($setup->getTable('core_config_data'), ['path LIKE ?' => $path]);
        }
    }
}
