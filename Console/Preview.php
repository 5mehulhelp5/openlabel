<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Console;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\IndexReader;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * openlabel:preview <label_id> [--store=<id>]: how many products a label matches and the first 20 SKUs, from the index.
 */
class Preview extends Command
{
    private const ARGUMENT_LABEL = 'label_id';
    private const OPTION_STORE = 'store';
    private const SKU_LIMIT = 20;

    /**
     * @param LabelRepositoryInterface $labelRepository
     * @param IndexReader $indexReader
     * @param StoreManagerInterface $storeManager
     * @param State $appState
     */
    public function __construct(
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly IndexReader $indexReader,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openlabel:preview')
            ->setDescription('Show how many products a label matches and the first 20 SKUs')
            ->addArgument(self::ARGUMENT_LABEL, InputArgument::REQUIRED, 'Label id')
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_REQUIRED, 'Store view id (default: the default store view)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Condition trees load UI component definitions, which need an area once the config cache is empty.
        return (int) $this->appState->emulateAreaCode(
            Area::AREA_ADMINHTML,
            fn (): int => $this->runCommand($input, $output)
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function runCommand(InputInterface $input, OutputInterface $output): int
    {
        try {
            $labelId = (int) $input->getArgument(self::ARGUMENT_LABEL);
            $label = $this->labelRepository->getById($labelId);
            $storeOption = $input->getOption(self::OPTION_STORE);
            $store = $storeOption === null ? $this->storeManager->getDefaultStoreView() : $this->storeManager->getStore((int) $storeOption);
            $storeId = (int) $store->getId();
            $count = $this->indexReader->countProducts($labelId, $storeId);
            $output->writeln(sprintf(
                'Label #%d "%s" matches %s products in store view "%s" (%d).',
                $labelId,
                $label->getName(),
                number_format($count),
                $store->getCode(),
                $storeId
            ));
            if ($count === 0) {
                $output->writeln(sprintf(
                    'Run openlabel:reindex %d if the label was just changed, and check that its conditions and store views match products.',
                    $labelId
                ));

                return Command::SUCCESS;
            }
            $skus = $this->indexReader->skus($labelId, $storeId, self::SKU_LIMIT);
            $output->writeln(sprintf('First %d SKUs:', count($skus)));
            foreach ($skus as $sku) {
                $output->writeln('  ' . $sku);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
