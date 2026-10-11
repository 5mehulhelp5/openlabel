<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Plugin;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelIndexingException;
use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Psr\Log\LoggerInterface;

/**
 * A saved label is reindexed at once (whatever the indexer mode) and a deleted label leaves no rows behind.
 */
class LabelRepositoryPlugin
{
    /**
     * @param LabelReindexer $labelReindexer
     * @param LoggerInterface $logger openlabel.log
     */
    public function __construct(
        private readonly LabelReindexer $labelReindexer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param LabelRepositoryInterface $subject
     * @param LabelInterface $result
     * @throws LabelIndexingException the label is saved, matching its products failed (logged)
     * @return LabelInterface
     */
    public function afterSave(LabelRepositoryInterface $subject, LabelInterface $result): LabelInterface
    {
        try {
            $this->labelReindexer->reindex($result);
        } catch (\Throwable $e) {
            $labelId = (int) $result->getLabelId();
            $this->logger->error(
                sprintf('Label #%d "%s": matching products failed: %s', $labelId, $result->getName(), $e->getMessage()),
                ['label_id' => $labelId, 'conditions' => $result->getConditionsSerialized(), 'exception' => $e]
            );
            throw new LabelIndexingException($labelId, $e);
        }

        return $result;
    }

    /**
     * @param LabelRepositoryInterface $subject
     * @param bool $result
     * @param LabelInterface $label
     * @return bool
     */
    public function afterDelete(LabelRepositoryInterface $subject, bool $result, LabelInterface $label): bool
    {
        if ($label->getLabelId() !== null) {
            $this->labelReindexer->removeLabel((int) $label->getLabelId());
        }

        return $result;
    }
}
