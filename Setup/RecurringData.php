<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Setup;

use Iranimij\OpenLabel\Model\Css\Regenerator;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs on every `setup:upgrade`: rebuilds the label stylesheet of every store, so a new version's structural CSS
 * (1.1: bands, text positions, motion, tooltips) reaches the shop without waiting for a design to be saved.
 * Never fails the upgrade: a problem is logged and `bin/magento openlabel:css:regenerate` can be run later.
 */
class RecurringData implements InstallDataInterface
{
    /**
     * @param Regenerator $regenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Regenerator $regenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->regenerator->regenerateAll();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'OpenLabel: the label stylesheet was not rebuilt during setup:upgrade (' . $e->getMessage()
                . '). Run bin/magento openlabel:css:regenerate.'
            );
        }
    }
}
