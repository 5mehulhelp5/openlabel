<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Console;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Indexer\Scheduler;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * openlabel:reindex [label_id]: rebuild the whole index (through the indexer, with its lock and cache cleaning)
 * or the rows of one label.
 */
class Reindex extends Command
{
    private const ARGUMENT_LABEL = 'label_id';

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param LabelReindexer $labelReindexer
     * @param LabelRepositoryInterface $labelRepository
     * @param State $appState
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly LabelReindexer $labelReindexer,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openlabel:reindex')
            ->setDescription('Rebuild the OpenLabel product index, or the rows of one label')
            ->addArgument(self::ARGUMENT_LABEL, InputArgument::OPTIONAL, 'Reindex only this label id');
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
        $start = microtime(true);
        $labelId = $input->getArgument(self::ARGUMENT_LABEL);
        try {
            if ($labelId === null || $labelId === '') {
                $this->indexerRegistry->get(Scheduler::INDEXER_ID)->reindexAll();
                $output->writeln(sprintf(
                    '<info>Full reindex done: %s index rows in %.2f s.</info>',
                    number_format($this->labelReindexer->countRows()),
                    microtime(true) - $start
                ));

                return Command::SUCCESS;
            }
            $id = (int) $labelId;
            $label = $this->labelRepository->getById($id);
            $diff = $this->labelReindexer->reindexLabel($id);
            $output->writeln(sprintf(
                '<info>Label #%d "%s": %d products (+%d / -%d) in %.2f s.</info>',
                $id,
                $label->getName(),
                count($this->labelReindexer->productIds($id)),
                count($diff->added),
                count($diff->removed),
                microtime(true) - $start
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
