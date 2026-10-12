<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Block;

use Iranimij\OpenLabel\Model\Css\Regenerator;
use Iranimij\OpenLabel\Model\Css\Storage;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Psr\Log\LoggerInterface;

/**
 * The single `<link>` to the store's generated stylesheet (10 · Front-end Review FE2), added to `head.additional`
 * by the theme package. The file name carries a content hash, so the browser can cache it forever.
 */
class Css extends AbstractBlock
{
    /**
     * @param Context $context
     * @param Labels $labels
     * @param Regenerator $regenerator
     * @param Storage $storage
     * @param LoggerInterface $logger
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Labels $labels,
        private readonly Regenerator $regenerator,
        private readonly Storage $storage,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string|null
     */
    public function getStylesheetUrl(): ?string
    {
        // Also on pages without labels: mini cart labels (customer data) appear there and need their styles.
        if (!$this->labels->isModuleEnabled()) {
            return null;
        }
        $storeId = $this->labels->getStoreId();
        try {
            return $this->storage->url($storeId, $this->regenerator->ensure($storeId));
        } catch (\Throwable $e) {
            $this->logger->error('OpenLabel: no stylesheet for store ' . $storeId . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        $url = $this->getStylesheetUrl();

        return $url === null
            ? ''
            : '<link rel="stylesheet" href="' . $this->escapeUrl($url) . '" data-openlabel="css">' . "\n";
    }
}
