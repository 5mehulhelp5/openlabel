<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration;

use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class AclTest extends TestCase
{
    public function testEveryOpenLabelResourceIsDeclared(): void
    {
        $ids = [];
        $walk = static function (array $resources) use (&$walk, &$ids): void {
            foreach ($resources as $resource) {
                $ids[] = $resource['id'];
                $walk($resource['children'] ?? []);
            }
        };
        $walk(Bootstrap::getObjectManager()->get(ProviderInterface::class)->getAclResources());

        foreach ([
            'Iranimij_OpenLabel::config',
            'Iranimij_OpenLabel::openlabel',
            'Iranimij_OpenLabel::labels',
            'Iranimij_OpenLabel::designs',
            'Iranimij_OpenLabel::custom_css',
            'Iranimij_OpenLabel::global_css',
        ] as $id) {
            self::assertContains($id, $ids, "ACL resource $id must be declared");
        }
    }
}
