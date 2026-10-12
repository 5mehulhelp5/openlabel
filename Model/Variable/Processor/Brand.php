<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Variable\Processor;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\VariableProcessorInterface;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Value;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * {BRAND}: the display value of the brand attribute set in the configuration (default `manufacturer`), read like
 * {ATTR:code}. On listings the attribute must be "Used in Product Listing" to be loaded.
 */
class Brand implements VariableProcessorInterface
{
    public const XML_ATTRIBUTE = 'openlabel/variables/brand_attribute';

    /**
     * @param Attr $attr
     * @param TypedReader $config
     */
    public function __construct(
        private readonly Attr $attr,
        private readonly TypedReader $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'BRAND';
    }

    /**
     * @inheritDoc
     */
    public function getValue(ProductInterface $product, Context $context, string $argument = ''): Value
    {
        $code = trim($this->config->getString(self::XML_ATTRIBUTE, ScopeInterface::SCOPE_STORE, $context->storeId));

        return $code === '' ? Value::empty() : $this->attr->getValue($product, $context, $code);
    }
}
