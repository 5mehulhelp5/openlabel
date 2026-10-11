<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\GraphQl;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\ResolvedLabelInterface;
use Iranimij\OpenLabel\Model\Css\DesignRules;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\Variable\Context;
use Iranimij\OpenLabel\Model\Variable\Renderer;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * One resolved label as the structured `OpenLabelLabel` GraphQL type (02 · Architecture section 7): plain text with
 * the variables filled in, never HTML. `design_id` is not part of the schema; the cache identity reads it.
 */
class LabelData
{
    /**
     * @param Renderer $renderer
     * @param DesignRules $designRules
     * @param ImageUrl $imageUrl
     */
    public function __construct(
        private readonly Renderer $renderer,
        private readonly DesignRules $designRules,
        private readonly ImageUrl $imageUrl
    ) {
    }

    /**
     * @param ResolvedLabelInterface $label
     * @param ProductInterface $product
     * @param Context $context
     * @return array<string, mixed>
     */
    public function toArray(ResolvedLabelInterface $label, ProductInterface $product, Context $context): array
    {
        $design = $label->getDesign();
        $designId = (int) $design->getDesignId();
        $isImage = $design->getType() === DesignInterface::TYPE_IMAGE;
        $classes = ['ol-label', 'ol-label--' . $design->getType(), $this->designRules->designClass($designId)];
        if (!$isImage && (string) $design->getShape() !== '') {
            $classes[] = 'ol-shape-' . $design->getShape();
        }
        $placement = $label->getPlacement();

        return [
            'id' => $label->getLabelId(),
            'name' => $label->getName(),
            'type' => $design->getType(),
            'text' => $isImage ? null : $this->plainText((string) $label->getText(), $product, $context),
            'image_url' => $isImage ? $this->imageUrl->get($design->getImagePath()) : null,
            'alt' => $isImage ? $this->nullIfEmpty($label->getAltText()) : null,
            'position' => $placement->getPosition(),
            'area' => $placement->getArea(),
            'css_class' => implode(' ', $classes),
            'bg_color' => $this->nullIfEmpty($design->getBgColor()),
            'text_color' => $this->nullIfEmpty($design->getTextColor()),
            'priority' => $label->getPriority(),
            'design_id' => $designId,
        ];
    }

    /**
     * Variables rendered for the store's locale, then the allow-listed markup dropped: `{BR}` becomes a line break.
     *
     * @param string $text
     * @param ProductInterface $product
     * @param Context $context
     * @return string
     */
    private function plainText(string $text, ProductInterface $product, Context $context): string
    {
        $html = $this->renderer->render($text, $product, $context)->getHtml();
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
