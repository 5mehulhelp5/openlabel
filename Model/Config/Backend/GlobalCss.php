<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Config\Backend;

use Iranimij\OpenLabel\Model\Css\Regenerator;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Global CSS setting: sanitized like design custom CSS before it is stored; a change rebuilds the stylesheet.
 */
class GlobalCss extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Sanitizer $sanitizer
     * @param Regenerator $regenerator
     * @param AbstractResource|null $resource
     * @param AbstractDb<\Magento\Framework\Model\AbstractModel>|null $resourceCollection
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Sanitizer $sanitizer,
        private readonly Regenerator $regenerator,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        $this->setValue((string) $this->sanitizer->sanitize((string) $this->getValue()));

        return parent::beforeSave();
    }

    /**
     * @return $this
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->regenerator->regenerateAll((string) $this->getValue());
        }

        return parent::afterSave();
    }
}
