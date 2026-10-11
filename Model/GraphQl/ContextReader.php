<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\GraphQl;

use Iranimij\OpenLabel\Model\Variable\Context;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\GraphQl\Model\Query\ContextInterface as QueryContextInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Store view, website and customer group of a GraphQL request: the store from the `Store` header, the group from
 * the customer token (core CustomerGraphQl puts it into the context); guests are NOT LOGGED IN.
 */
class ContextReader
{
    /**
     * @param ContextInterface $context
     * @return Context
     */
    public function read(ContextInterface $context): Context
    {
        if (!$context instanceof QueryContextInterface) {
            throw new \InvalidArgumentException('The GraphQL context carries no store and customer group.');
        }
        $extension = $context->getExtensionAttributes();
        /** @var StoreInterface $store */
        $store = $extension->getStore();
        $group = $extension->getCustomerGroupId();

        return new Context(
            (int) $store->getId(),
            (int) $store->getWebsiteId(),
            $group === null ? GroupInterface::NOT_LOGGED_IN_ID : (int) $group
        );
    }
}
