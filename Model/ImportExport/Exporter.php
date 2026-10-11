<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Labels and the designs they use as one portable JSON document (see FileFormat). Image designs carry their file
 * as base64; built-in designs are referenced by name only.
 */
class Exporter
{
    /**
     * @param LabelRepositoryInterface $labelRepository
     * @param DesignRepositoryInterface $designRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeCodes $scopeCodes
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ScopeCodes $scopeCodes,
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * @param int[]|null $labelIds null exports every label
     * @return array<string, mixed>
     */
    public function export(?array $labelIds = null): array
    {
        $this->scopeCodes->reset();
        if ($labelIds !== null) {
            $this->searchCriteriaBuilder->addFilter(LabelInterface::LABEL_ID, $labelIds, 'in');
        }
        $labels = $this->labelRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        usort($labels, static fn (LabelInterface $a, LabelInterface $b): int => (int) $a->getLabelId() <=> (int) $b->getLabelId());

        $designs = [];
        $labelRows = [];
        foreach ($labels as $label) {
            $designs[(int) $label->getDesignId()] = true;
            foreach ($label->getPlacements() as $placement) {
                if ($placement->getDesignId() !== null) {
                    $designs[$placement->getDesignId()] = true;
                }
            }
        }
        $designRows = [];
        $keys = [];
        foreach (array_keys($designs) as $designId) {
            $design = $this->designRepository->getById($designId);
            $keys[$designId] = $this->key($design);
            $designRows[] = $this->designRow($design, $keys[$designId]);
        }
        foreach ($labels as $label) {
            $labelRows[] = $this->labelRow($label, $keys);
        }

        return [
            'format' => FileFormat::NAME,
            'version' => FileFormat::VERSION,
            'designs' => $designRows,
            'labels' => $labelRows,
        ];
    }

    /**
     * @param DesignInterface $design
     * @return string
     */
    private function key(DesignInterface $design): string
    {
        return $design->isSystem()
            ? FileFormat::SYSTEM_PREFIX . $design->getName()
            : FileFormat::DESIGN_PREFIX . (int) $design->getDesignId();
    }

    /**
     * @param DesignInterface $design
     * @param string $key
     * @return array<string, mixed>
     */
    private function designRow(DesignInterface $design, string $key): array
    {
        if ($design->isSystem()) {
            return ['key' => $key, 'name' => $design->getName(), 'is_system' => true];
        }
        $texts = [];
        foreach ($design->getStoreTexts() as $storeId => $row) {
            $texts[$this->scopeCodes->storeCode((int) $storeId)] = [
                'text' => $row['text'] ?? null,
                'alt_text' => $row['alt_text'] ?? null,
                'tooltip' => $row['tooltip'] ?? null,
            ];
        }

        return [
            'key' => $key,
            'name' => $design->getName(),
            'type' => $design->getType(),
            'shape' => $design->getShape(),
            'bg_color' => $design->getBgColor(),
            'text_color' => $design->getTextColor(),
            'border_color' => $design->getBorderColor(),
            'border_width' => $design->getBorderWidth(),
            'font_size' => $design->getFontSize(),
            'size_mode' => $design->getSizeMode(),
            'width' => $design->getWidth(),
            'height' => $design->getHeight(),
            'opacity' => $design->getOpacity(),
            'rotation' => $design->getRotation(),
            'animation' => $design->getAnimation(),
            'hover_effect' => $design->getHoverEffect(),
            'sizes' => $design->getSizes(),
            'custom_css' => $design->getCustomCss(),
            'store_texts' => $texts,
            'image' => $this->image($design),
        ];
    }

    /**
     * @param DesignInterface $design
     * @return array<string, mixed>|null
     */
    private function image(DesignInterface $design): ?array
    {
        $path = (string) $design->getImagePath();
        if ($design->getType() !== DesignInterface::TYPE_IMAGE || $path === '') {
            return null;
        }
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $file = ImageUrl::MEDIA_DIR . '/' . $path;
        if (!$media->isFile($file)) {
            return null;
        }

        return [
            'file' => substr($path, (int) strrpos('/' . $path, '/')),
            'width' => $design->getImageWidth(),
            'height' => $design->getImageHeight(),
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- the image travels inside the JSON file
            'content' => base64_encode($media->readFile($file)),
        ];
    }

    /**
     * @param LabelInterface $label
     * @param array<int, string> $keys design id → key
     * @return array<string, mixed>
     */
    private function labelRow(LabelInterface $label, array $keys): array
    {
        $conditions = $label->getConditionsSerialized();
        $placements = $label->getPlacements();
        usort($placements, static fn (PlacementInterface $a, PlacementInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder());

        return [
            'name' => $label->getName(),
            'status' => $label->getStatus(),
            'priority' => $label->getPriority(),
            'stores' => array_map(fn (int $id): string => $this->scopeCodes->storeCode($id), $label->getStoreIds()),
            'customer_groups' => array_map(fn (int $id): string => $this->scopeCodes->groupCode($id), $label->getCustomerGroupIds()),
            'valid_from' => $label->getValidFrom(),
            'valid_to' => $label->getValidTo(),
            'conditions' => $conditions === null || $conditions === '' ? null : $this->json->unserialize($conditions),
            'design' => $keys[(int) $label->getDesignId()],
            'apply_to_parent' => $label->isApplyToParent(),
            'hide_lower_priority' => $label->isHideLowerPriority(),
            'hide_on_zero_variable' => $label->isHideOnZeroVariable(),
            'click_url' => $label->getClickUrl(),
            'click_nofollow' => $label->isClickNofollow(),
            'placements' => array_map(
                static fn (PlacementInterface $placement): array => [
                    'area' => $placement->getArea(),
                    'position' => $placement->getPosition(),
                    'pin_physical_side' => $placement->isPinPhysicalSide(),
                    'design' => $placement->getDesignId() === null ? null : $keys[$placement->getDesignId()],
                    'offset_x' => $placement->getOffsetX(),
                    'offset_y' => $placement->getOffsetY(),
                    'max_labels' => $placement->getMaxLabels(),
                    'stacking' => $placement->getStacking(),
                    'gap' => $placement->getGap(),
                    'sort_order' => $placement->getSortOrder(),
                ],
                $placements
            ),
        ];
    }
}
