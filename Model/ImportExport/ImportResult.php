<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

/**
 * What an import did, or would do in a dry run. With errors nothing was written.
 */
class ImportResult
{
    /**
     * @param string[] $errors
     * @param int $designsCreated
     * @param int $designsUpdated
     * @param int $designsReused built-in designs found in this shop
     * @param int $labelsCreated
     * @param int $labelsUpdated
     * @param int[] $labelIds labels written (empty in a dry run)
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly int $designsCreated = 0,
        public readonly int $designsUpdated = 0,
        public readonly int $designsReused = 0,
        public readonly int $labelsCreated = 0,
        public readonly int $labelsUpdated = 0,
        public readonly array $labelIds = []
    ) {
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }
}
