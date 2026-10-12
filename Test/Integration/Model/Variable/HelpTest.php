<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Variable;

use Iranimij\OpenLabel\Model\Variable\Help;
use Iranimij\OpenLabel\Model\Variable\Pool;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Every variable the engine knows has a one-line explanation and a preview sample (08 · UX Spec §6).
 */
class HelpTest extends TestCase
{
    public function testEveryRegisteredVariableHasHelpAndASample(): void
    {
        $om = Bootstrap::getObjectManager();
        $codes = array_map(static fn ($processor): string => $processor->getCode(), array_values($om->get(Pool::class)->getAll()));
        $help = $om->get(Help::class)->getVariables();

        self::assertCount(17, $codes);
        foreach ($codes as $code) {
            $key = $code === 'ATTR' ? 'ATTR:code' : $code;
            self::assertArrayHasKey($key, $help, "help for {$code}");
            self::assertNotSame('', $help[$key]['description']);
            self::assertNotSame('', $help[$key]['sample']);
        }
    }
}
