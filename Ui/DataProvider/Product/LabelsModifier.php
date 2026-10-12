<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Ui\DataProvider\Product;

use Iranimij\OpenLabel\Model\Indexer\IndexReader;
use Iranimij\OpenLabel\Model\Label\ManualAssignments;
use Iranimij\OpenLabel\Model\ResourceModel\Label\CollectionFactory;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;

/**
 * "OpenLabel" section of the product edit page (1.1): the labels the product shows now (from the index, with the
 * ones assigned by hand marked) and a multiselect of labels it always shows. Saved by Observer\SaveProductLabels.
 */
class LabelsModifier extends AbstractModifier
{
    public const FIELD = 'openlabel_label_ids';
    public const TOUCHED = 'openlabel_touched';
    private const ACL = 'Iranimij_OpenLabel::labels';

    /**
     * @param LocatorInterface $locator
     * @param ManualAssignments $assignments
     * @param IndexReader $indexReader
     * @param CollectionFactory $labelCollectionFactory
     * @param AuthorizationInterface $authorization
     * @param UrlInterface $url
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly ManualAssignments $assignments,
        private readonly IndexReader $indexReader,
        private readonly CollectionFactory $labelCollectionFactory,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    public function modifyData(array $data)
    {
        $productId = (int) $this->locator->getProduct()->getId();
        if (!$this->authorization->isAllowed(self::ACL)) {
            return $data;
        }
        $data[$productId]['product'][self::FIELD] = array_map('strval', $productId > 0 ? $this->assignments->forProduct($productId) : []);
        $data[$productId]['product'][self::TOUCHED] = '1';

        return $data;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function modifyMeta(array $meta)
    {
        if (!$this->authorization->isAllowed(self::ACL)) {
            return $meta;
        }
        $meta['openlabel'] = [
            'arguments' => ['data' => ['config' => [
                'componentType' => 'fieldset',
                'label' => __('OpenLabel'),
                'collapsible' => true,
                'opened' => false,
                'dataScope' => 'data.product',
                'sortOrder' => 1000,
            ]]],
            'children' => [
                'openlabel_applied' => ['arguments' => ['data' => ['config' => [
                    'componentType' => 'container',
                    'component' => 'Magento_Ui/js/form/components/html',
                    'content' => $this->appliedHtml(),
                    'sortOrder' => 10,
                ]]]],
                self::FIELD => ['arguments' => ['data' => ['config' => [
                    'componentType' => 'field',
                    'formElement' => 'multiselect',
                    'dataType' => 'text',
                    'label' => __('Always show these labels'),
                    'notice' => __('Shown on this product whatever their conditions, within their store views, customer groups and dates. Saving reindexes the labels you change.'),
                    'dataScope' => self::FIELD,
                    'options' => $this->labelOptions(),
                    'sortOrder' => 20,
                ]]]],
                self::TOUCHED => ['arguments' => ['data' => ['config' => [
                    'componentType' => 'field',
                    'formElement' => 'hidden',
                    'dataType' => 'text',
                    'dataScope' => self::TOUCHED,
                ]]]],
            ],
        ];

        return $meta;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function labelOptions(): array
    {
        $options = [];
        foreach ($this->labelCollectionFactory->create()->setOrder('name', 'ASC')->getData() as $row) {
            $name = (string) $row['name'];
            $options[] = [
                'value' => (string) $row['label_id'],
                'label' => (int) $row['status'] === 1 ? $name : (string) __('%1 (off)', $name),
            ];
        }

        return $options;
    }

    /**
     * @return string
     */
    private function appliedHtml(): string
    {
        $productId = (int) $this->locator->getProduct()->getId();
        if ($productId === 0) {
            return '<p>' . $this->escaper->escapeHtml((string) __('Labels are matched after the product is saved.')) . '</p>';
        }
        $labels = $this->indexReader->labelsOfProduct($productId);
        if ($labels === []) {
            return '<p>' . $this->escaper->escapeHtml((string) __('This product shows no labels at the moment.')) . '</p>';
        }
        $manual = $this->assignments->forProduct($productId);
        $items = '';
        foreach ($labels as $labelId => $name) {
            $items .= '<li><a href="' . $this->escaper->escapeUrl($this->url->getUrl('openlabel/label/edit', ['id' => $labelId])) . '">'
                . $this->escaper->escapeHtml($name) . '</a>'
                . (in_array($labelId, $manual, true) ? ' ' . $this->escaper->escapeHtml((string) __('(by hand)')) : '')
                . '</li>';
        }

        return '<p>' . $this->escaper->escapeHtml((string) __('Labels this product shows now:')) . '</p><ul class="ol-product-labels">' . $items . '</ul>';
    }
}
