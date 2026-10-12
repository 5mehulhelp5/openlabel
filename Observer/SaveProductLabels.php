<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Observer;

use Iranimij\OpenLabel\Model\Indexer\LabelReindexer;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Ui\DataProvider\Product\LabelsModifier;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * `controller_action_catalog_product_save_entity_after`: saves the labels assigned by hand in the product's
 * "OpenLabel" section and reindexes the labels that gained or lost the product.
 */
class SaveProductLabels implements ObserverInterface
{
    /**
     * @param ManualAssignments $assignments
     * @param LabelReindexer $reindexer
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly ManualAssignments $assignments,
        private readonly LabelReindexer $reindexer,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getData('product');
        $controller = $observer->getData('controller');
        if (!$product instanceof ProductInterface || !is_object($controller) || !method_exists($controller, 'getRequest')
            || !$this->authorization->isAllowed('Iranimij_OpenLabel::labels')
        ) {
            return;
        }
        $post = $controller->getRequest()->getParam('product');
        if (!is_array($post) || (string) ($post[LabelsModifier::TOUCHED] ?? '') !== '1') {
            return;
        }
        $labelIds = $post[LabelsModifier::FIELD] ?? [];
        $changed = $this->assignments->setForProduct((int) $product->getId(), is_array($labelIds) ? $labelIds : []);
        foreach ($changed as $labelId) {
            $this->reindexer->reindexLabel($labelId);
        }
    }
}
