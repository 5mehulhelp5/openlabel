<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Indexer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * The label was saved, but matching its products failed; the cause is in var/log/openlabel.log.
 */
class LabelIndexingException extends LocalizedException
{
    /**
     * @param int $labelId
     * @param \Throwable $cause
     */
    public function __construct(private readonly int $labelId, \Throwable $cause)
    {
        parent::__construct(
            new Phrase(
                'The label was saved, but its products could not be matched: %1 Details are in var/log/openlabel.log.',
                [$cause->getMessage()]
            ),
            $cause instanceof \Exception ? $cause : null
        );
    }

    /**
     * @return int
     */
    public function getLabelId(): int
    {
        return $this->labelId;
    }
}
