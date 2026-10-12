<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Plugin\Webapi;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignSearchResultsInterface;
use Magento\Framework\Webapi\ServiceOutputProcessor;

/**
 * A design's `store_texts` and `sizes` are `UnstructuredArray` in the web API. Magento 2.4.9 outputs them as they
 * are; 2.4.7 and 2.4.8 cast them element by element and drop their keys (store view IDs, breakpoints). This puts
 * them back for one design and for every design of a search result (webapi_rest and webapi_soap areas only).
 */
class DesignOutputPlugin
{
    private const FIELDS = ['store_texts' => 'getStoreTexts', 'sizes' => 'getSizes'];

    /**
     * @param ServiceOutputProcessor $subject
     * @param mixed $result the output array
     * @param mixed $data what the service method returned
     * @param string $serviceClassName
     * @param string $serviceMethodName
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterProcess(
        ServiceOutputProcessor $subject,
        mixed $result,
        mixed $data,
        string $serviceClassName,
        string $serviceMethodName
    ): mixed {
        if (!is_array($result)) {
            return $result;
        }
        if ($data instanceof DesignInterface) {
            return $this->restore($result, $data);
        }
        if ($data instanceof DesignSearchResultsInterface && isset($result['items']) && is_array($result['items'])) {
            foreach (array_values($data->getItems()) as $index => $design) {
                if (isset($result['items'][$index]) && is_array($result['items'][$index])) {
                    $result['items'][$index] = $this->restore($result['items'][$index], $design);
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $output
     * @param DesignInterface $design
     * @return array<string, mixed>
     */
    private function restore(array $output, DesignInterface $design): array
    {
        foreach (self::FIELDS as $field => $getter) {
            if (array_key_exists($field, $output)) {
                $output[$field] = $design->$getter();
            }
        }

        return $output;
    }
}
