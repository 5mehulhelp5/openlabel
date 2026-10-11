<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Console;

use Iranimij\OpenLabel\Model\ImportExport\Exporter;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento openlabel:export [file] [--labels=1,2]`: labels and their designs as JSON, to a file or to stdout.
 */
class Export extends Command
{
    private const ARGUMENT_FILE = 'file';
    private const OPTION_LABELS = 'labels';

    /**
     * @param Exporter $exporter
     * @param File $fileDriver
     * @param State $appState
     */
    public function __construct(
        private readonly Exporter $exporter,
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
        $this->setName('openlabel:export')
            ->setDescription('Export labels and their designs as JSON (to a file, or to stdout without one)')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::OPTIONAL, 'Target file')
            ->addOption(self::OPTION_LABELS, 'l', InputOption::VALUE_REQUIRED, 'Comma-separated label ids (default: all)');
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
        try {
            $ids = $input->getOption(self::OPTION_LABELS);
            $labelIds = $ids === null
                ? null
                : array_values(array_filter(array_map('intval', explode(',', (string) $ids))));
            $data = $this->exporter->export($labelIds);
            $content = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            $file = $input->getArgument(self::ARGUMENT_FILE);
            if ($file === null) {
                $output->write($content, false, OutputInterface::OUTPUT_RAW);

                return Command::SUCCESS;
            }
            $this->fileDriver->filePutContents((string) $file, $content);
            $output->writeln(sprintf(
                'Exported %d label(s) and %d design(s) to %s.',
                count($data['labels']),
                count($data['designs']),
                (string) $file
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
