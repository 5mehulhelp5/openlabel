<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Console;

use Iranimij\OpenLabel\Model\ImportExport\Importer;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento openlabel:import <file> [--dry-run] [--replace]`: all or nothing; errors list what to fix.
 */
class Import extends Command
{
    private const ARGUMENT_FILE = 'file';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_REPLACE = 'replace';

    /**
     * @param Importer $importer
     * @param File $fileDriver
     * @param State $appState
     */
    public function __construct(
        private readonly Importer $importer,
        private readonly File $fileDriver,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openlabel:import')
            ->setDescription('Import labels and designs from an openlabel:export JSON file (all or nothing)')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'JSON file written by openlabel:export')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Check the file against this shop, write nothing')
            ->addOption(self::OPTION_REPLACE, null, InputOption::VALUE_NONE, 'Update labels and designs with the same name instead of adding new ones');
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
        $file = (string) $input->getArgument(self::ARGUMENT_FILE);
        try {
            $data = json_decode((string) $this->fileDriver->fileGetContents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s cannot be read as JSON: %s</error>', $file, $e->getMessage()));

            return Command::FAILURE;
        }
        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $result = $this->importer->import(is_array($data) ? $data : [], $dryRun, (bool) $input->getOption(self::OPTION_REPLACE));
        if (!$result->isSuccessful()) {
            $output->writeln('<error>Nothing was imported. Fix these and run again:</error>');
            foreach ($result->errors as $error) {
                $output->writeln('  - ' . $error);
            }

            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            '%s %d label(s) new, %d updated; designs: %d new, %d updated, %d built-in reused.',
            $dryRun ? 'Dry run, nothing written. Would import:' : 'Imported:',
            $result->labelsCreated,
            $result->labelsUpdated,
            $result->designsCreated,
            $result->designsUpdated,
            $result->designsReused
        ));

        return Command::SUCCESS;
    }
}
