<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds the built-in designs of 1.1 on upgrade. Same idempotent rules as the 1.0 patch: existing system designs
 * (matched by name) are left alone, missing ones are created.
 */
class SystemDesignsOneDotOne implements DataPatchInterface
{
    /**
     * @param SystemDesigns $systemDesigns
     */
    public function __construct(private readonly SystemDesigns $systemDesigns)
    {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->systemDesigns->apply();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [SystemDesigns::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
