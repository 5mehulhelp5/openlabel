<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Console;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\ImportExport\Importer;
use Iranimij\OpenLabel\Model\ImportExport\Smile\Converter;
use Iranimij\OpenLabel\Model\ImportExport\Smile\Reader;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento openlabel:import-smile [--dry-run]`: copies the labels of the open-source Smile product label module
 * into OpenLabel. Labels already imported (same name) are skipped, so the command can run again.
 */
class ImportSmile extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param Reader $reader
     * @param Converter $converter
     * @param Importer $importer
     * @param LabelRepositoryInterface $labelRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param State $appState
     */
    public function __construct(
        private readonly Reader $reader,
        private readonly Converter $converter,
        private readonly Importer $importer,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openlabel:import-smile')
            ->setDescription('Import the labels of the Smile product label module (smile_productlabel tables)')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Check only, write nothing');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
        if (!$this->reader->hasTables()) {
            $output->writeln('<error>No Smile product label tables (smile_productlabel) in this database.</error>');

            return Command::FAILURE;
        }
        $existing = [];
        foreach ($this->labelRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $label) {
            $existing[$label->getName()] = true;
        }
        $rows = [];
        $skipped = 0;
        foreach ($this->reader->read() as $row) {
            if (isset($existing[(string) $row['name']])) {
                $skipped++;
                continue;
            }
            $rows[] = $row;
        }
        $converted = $this->converter->convert($rows);
        foreach ($converted['warnings'] as $warning) {
            $output->writeln('<comment>' . $warning . '</comment>');
        }
        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $result = $this->importer->import($converted['file'], $dryRun);
        if (!$result->isSuccessful()) {
            $output->writeln('<error>Nothing was imported. Fix these and run again:</error>');
            foreach ($result->errors as $error) {
                $output->writeln('  - ' . $error);
            }

            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            '%s %d Smile label(s)%s.',
            $dryRun ? 'Dry run, nothing written. Would import' : 'Imported',
            $result->labelsCreated,
            $skipped > 0 ? sprintf('; %d already imported earlier, skipped', $skipped) : ''
        ));

        return Command::SUCCESS;
    }
}
