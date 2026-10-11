<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\GraphQl;

use Iranimij\OpenLabel\Model\Design;
use Iranimij\OpenLabel\Model\Label;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

/**
 * Cache tags of a cached GraphQL GET response: `openlabel_<label_id>` and `openlabel_design_<design_id>`, the same
 * tags label and design saves already clean for pages.
 */
class Identity implements IdentityInterface
{
    /**
     * @param array<int|string, mixed> $resolvedData the `openlabel_labels` list of one product
     * @return string[]
     */
    public function getIdentities(array $resolvedData): array
    {
        $tags = [];
        foreach ($resolvedData as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['id'])) {
                $tags[Label::CACHE_TAG_PREFIX . (int) $item['id']] = true;
            }
            if (isset($item['design_id'])) {
                $tags[Design::CACHE_TAG . '_' . (int) $item['design_id']] = true;
            }
        }
        $tags = array_keys($tags);
        sort($tags);

        return $tags;
    }
}
