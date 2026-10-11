<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Block\Adminhtml\Label\Edit;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\IndexReader;
use Iranimij\OpenLabel\Model\Label\ConditionDiagnosis;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * "Matched products" in the label form (08 · UX Spec §4.6): how many products have the label in the default
 * store view, the first 50 with thumbnails, an explanation when there are none, and "Reindex now".
 */
class MatchedProducts extends Template
{
    public const LIMIT = 50;

    /**
     * @var string
     */
    protected $_template = 'Iranimij_OpenLabel::label/matched-products.phtml';

    /**
     * @param Context $context
     * @param IndexReader $indexReader
     * @param CollectionFactory $productCollectionFactory
     * @param Image $imageHelper
     * @param LabelRepositoryInterface $labelRepository
     * @param ConditionDiagnosis $conditionDiagnosis
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly IndexReader $indexReader,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Image $imageHelper,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly ConditionDiagnosis $conditionDiagnosis,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return int 0 for a label that was not saved yet
     */
    public function getLabelId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return (int) $this->_storeManager->getDefaultStoreView()?->getId();
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->getLabelId() === 0 ? 0 : $this->indexReader->countProducts($this->getLabelId(), $this->getStoreId());
    }

    /**
     * @return array<int, array{sku: string, name: string, image: string, url: string}>
     */
    public function getProducts(): array
    {
        $skus = $this->indexReader->skus($this->getLabelId(), $this->getStoreId(), self::LIMIT);
        if ($skus === []) {
            return [];
        }
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'thumbnail', 'small_image'])
            ->addFieldToFilter('sku', ['in' => $skus]);
        $bySku = [];
        foreach ($collection as $product) {
            $bySku[(string) $product->getSku()] = [
                'sku' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'image' => $this->imageHelper->init($product, 'product_listing_thumbnail')->getUrl(),
                'url' => $this->getUrl('catalog/product/edit', ['id' => $product->getId()]),
            ];
        }
        $products = [];
        foreach ($skus as $sku) {
            if (isset($bySku[$sku])) {
                $products[] = $bySku[$sku];
            }
        }

        return $products;
    }

    /**
     * @return string
     */
    public function getReindexUrl(): string
    {
        return $this->getUrl('openlabel/label/reindex');
    }

    /**
     * Why a saved label matches no products: reasons on the label itself, then each condition on its own.
     *
     * @return array{reasons: string[], conditions: array<int, array{text: string, count: int, level: int}>}
     */
    public function getDiagnosis(): array
    {
        try {
            $label = $this->labelRepository->getById($this->getLabelId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return ['reasons' => [], 'conditions' => []];
        }
        $storeId = $this->getStoreId();
        $reasons = [];
        if ($label->getStatus() !== \Iranimij\OpenLabel\Api\Data\LabelInterface::STATUS_ENABLED) {
            $reasons[] = (string) __('The label is switched off.');
        }
        $stores = $label->getStoreIds();
        if ($stores !== [] && !in_array(0, $stores, true) && !in_array($storeId, $stores, true)) {
            $reasons[] = (string) __('The label is not shown in the default store view (see "Who and when").');
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($label->getValidFrom() !== null && $label->getValidFrom() > $now) {
            $reasons[] = (string) __('The label starts later (see "Who and when").');
        }
        if ($label->getValidTo() !== null && $label->getValidTo() < $now) {
            $reasons[] = (string) __('The label has ended (see "Who and when").');
        }

        return ['reasons' => $reasons, 'conditions' => $this->conditionDiagnosis->diagnose($label, $storeId)];
    }
}
