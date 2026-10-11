<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\GraphQl;

use Iranimij\OpenLabel\Model\GraphQl\Identity;
use PHPUnit\Framework\TestCase;

class IdentityTest extends TestCase
{
    public function testTagsEveryLabelAndDesignOnce(): void
    {
        $tags = (new Identity())->getIdentities([
            ['id' => 1, 'design_id' => 10],
            ['id' => 1, 'design_id' => 12],
            ['id' => 4, 'design_id' => 10],
        ]);

        self::assertSame(
            ['openlabel_1', 'openlabel_4', 'openlabel_design_10', 'openlabel_design_12'],
            $tags
        );
    }

    public function testNoLabelsNoTags(): void
    {
        self::assertSame([], (new Identity())->getIdentities([]));
    }

    public function testIgnoresEntriesWithoutIds(): void
    {
        self::assertSame(['openlabel_2'], (new Identity())->getIdentities([['id' => 2], 'garbage', ['name' => 'x']]));
    }
}
