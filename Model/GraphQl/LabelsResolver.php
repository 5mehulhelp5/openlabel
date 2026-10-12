<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\GraphQl;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;
use Iranimij\OpenLabel\Api\LabelResolverInterface;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;

/**
 * `ProductInterface.openlabel_labels`: all products of a query are resolved with one label lookup (one SELECT over
 * the index), for the request's store view and customer group. Only enabled labels inside their time window are
 * returned; conditions are never exposed.
 */
class LabelsResolver implements BatchResolverInterface
{
    /**
     * @param LabelResolverInterface $resolver
     * @param LabelData $labelData
     * @param Renderer $renderer
     * @param TypedReader $config
     * @param ContextReader $contextReader
     */
    public function __construct(
        private readonly LabelResolverInterface $resolver,
        private readonly LabelData $labelData,
        private readonly Renderer $renderer,
        private readonly TypedReader $config,
        private readonly ContextReader $contextReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(ContextInterface $context, Field $field, array $requests): BatchResponse
    {
        $response = new BatchResponse();
        $variables = $this->contextReader->read($context);
        $products = [];
        foreach ($requests as $request) {
            $product = $request->getValue()['model'] ?? null;
            if ($product instanceof ProductInterface && (int) $product->getId() > 0) {
                $products[(int) $product->getId()] = $product;
            }
        }
        $resolved = [];
        if ($products !== [] && $this->config->getBool(Labels::CONFIG_ENABLED, scopeId: $variables->storeId)) {
            $resolved = $this->resolver->getForProducts(
                array_keys($products),
                $variables->storeId,
                $variables->customerGroupId
            );
            $this->preload($products, $resolved, $variables);
        }
        foreach ($requests as $request) {
            $product = $request->getValue()['model'] ?? null;
            $items = [];
            if ($product instanceof ProductInterface) {
                foreach ($resolved[(int) $product->getId()] ?? [] as $label) {
                    $item = $this->labelData->toArray($label, $product, $variables);
                    if ($item !== null) {
                        $items[] = $item;
                    }
                }
            }
            $response->addResponse($request, $items);
        }

        return $response;
    }

    /**
     * Review and sales data once for all products, only for the variables the labels use.
     *
     * @param array<int, ProductInterface> $products
     * @param array<int, ResolvedLabelInterface[]> $resolved
     * @param Context $context
     * @return void
     */
    private function preload(array $products, array $resolved, Context $context): void
    {
        $texts = [];
        foreach ($resolved as $labels) {
            foreach ($labels as $label) {
                $text = (string) $label->getText();
                if (str_contains($text, '{')) {
                    $texts[$text] = $text;
                }
            }
        }
        if ($texts !== []) {
            $this->renderer->preloadFor(array_values($products), $context, array_values($texts));
        }
    }
}
