<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignInterfaceFactory;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterfaceFactory;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterfaceFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Css\Regenerator;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\Design\Validator as DesignValidator;
use Iranimij\OpenLabel\Model\Image\Validator as ImageValidator;
use Iranimij\OpenLabel\Model\Label\Validator as LabelValidator;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Imports an OpenLabel export file in two phases: first everything is checked against this shop (store and group
 * codes, design references, condition classes, the label and design validators, images through the upload
 * validator incl. SVG sanitizing, custom CSS through the sanitizer) without writing anything; then designs, images
 * and labels are written in one transaction. Labels are reindexed by the repository as they are saved.
 */
class Importer
{
    /** Placeholder design id for validating a label before its design exists. */
    private const PENDING_DESIGN = -1;

    /**
     * @param FileFormat $fileFormat
     * @param ConditionGuard $conditionGuard
     * @param ScopeCodes $scopeCodes
     * @param DesignRepositoryInterface $designRepository
     * @param LabelRepositoryInterface $labelRepository
     * @param DesignInterfaceFactory $designFactory
     * @param LabelInterfaceFactory $labelFactory
     * @param PlacementInterfaceFactory $placementFactory
     * @param DesignValidator $designValidator
     * @param LabelValidator $labelValidator
     * @param ImageValidator $imageValidator
     * @param Sanitizer $cssSanitizer
     * @param Regenerator $cssRegenerator
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Filesystem $filesystem
     * @param ResourceConnection $resource
     * @param Json $json
     * @param LoggerInterface $logger openlabel.log
     */
    public function __construct(
        private readonly FileFormat $fileFormat,
        private readonly ConditionGuard $conditionGuard,
        private readonly ScopeCodes $scopeCodes,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly DesignInterfaceFactory $designFactory,
        private readonly LabelInterfaceFactory $labelFactory,
        private readonly PlacementInterfaceFactory $placementFactory,
        private readonly DesignValidator $designValidator,
        private readonly LabelValidator $labelValidator,
        private readonly ImageValidator $imageValidator,
        private readonly Sanitizer $cssSanitizer,
        private readonly Regenerator $cssRegenerator,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Filesystem $filesystem,
        private readonly ResourceConnection $resource,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $data the decoded export file
     * @param bool $dryRun check only, write nothing
     * @param bool $replace update labels and designs with the same name instead of adding new ones
     * @return ImportResult
     */
    public function import(array $data, bool $dryRun = false, bool $replace = false): ImportResult
    {
        $this->scopeCodes->reset();
        $errors = $this->fileFormat->errors($data);
        if ($errors !== []) {
            return new ImportResult($errors);
        }

        // Phase 1: build and check everything, write nothing.
        $designs = [];
        foreach ($data['designs'] as $i => $row) {
            $designs[(string) $row['key']] = $this->prepareDesign($i, $row, $replace, $errors);
        }
        $labels = [];
        foreach ($data['labels'] as $i => $row) {
            $labels[] = $this->prepareLabel($i, $row, $designs, $replace, $errors);
        }
        if ($errors !== []) {
            return new ImportResult($errors);
        }
        $counts = $this->count($designs, $labels);
        if ($dryRun) {
            return new ImportResult([], ...$counts);
        }

        // Phase 2: write in one transaction.
        $connection = $this->resource->getConnection();
        $written = [];
        $labelIds = [];
        $connection->beginTransaction();
        try {
            foreach ($designs as $key => $prepared) {
                $designs[$key]['id'] = $this->saveDesign($prepared, $written);
            }
            foreach ($labels as $prepared) {
                $labelIds[] = $this->saveLabel($prepared, $designs);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('Import rolled back: ' . $e->getMessage(), ['exception' => $e]);
            $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            foreach ($written as $file) {
                $media->delete($file);
            }
            $this->cssRegenerator->regenerateAll();

            return new ImportResult([sprintf('Nothing was imported: %s Details are in var/log/openlabel.log.', $e->getMessage())]);
        }

        return new ImportResult([], ...array_merge($counts, ['labelIds' => $labelIds]));
    }

    /**
     * @param int $index
     * @param array<string, mixed> $row
     * @param bool $replace
     * @param string[] $errors
     * @return array<string, mixed>
     */
    private function prepareDesign(int $index, array $row, bool $replace, array &$errors): array
    {
        $key = (string) $row['key'];
        $where = sprintf('designs[%d] "%s"', $index, (string) ($row['name'] ?? $key));
        if (str_starts_with($key, FileFormat::SYSTEM_PREFIX)) {
            $name = substr($key, strlen(FileFormat::SYSTEM_PREFIX));
            $existing = $this->findDesign($name, true);
            if ($existing === null) {
                $errors[] = sprintf('%s: this shop has no built-in design named "%s".', $where, $name);
            }

            return ['system' => true, 'id' => $existing?->getDesignId()];
        }

        $existing = $replace ? $this->findDesign((string) ($row['name'] ?? ''), false) : null;
        $design = $existing ?? $this->designFactory->create();
        $design->setName((string) ($row['name'] ?? ''));
        $design->setType((string) ($row['type'] ?? DesignInterface::TYPE_TEXT));
        $design->setShape(isset($row['shape']) ? (string) $row['shape'] : null);
        $design->setBgColor(isset($row['bg_color']) ? (string) $row['bg_color'] : null);
        $design->setTextColor(isset($row['text_color']) ? (string) $row['text_color'] : null);
        $design->setBorderColor(isset($row['border_color']) ? (string) $row['border_color'] : null);
        $design->setBorderWidth((int) ($row['border_width'] ?? 0));
        $design->setFontSize((int) ($row['font_size'] ?? 14));
        $design->setSizeMode((string) ($row['size_mode'] ?? DesignInterface::SIZE_MODE_PERCENT));
        $design->setWidth((int) ($row['width'] ?? 18));
        $design->setHeight(isset($row['height']) ? (int) $row['height'] : null);
        $design->setOpacity((int) ($row['opacity'] ?? 100));
        $design->setRotation((int) ($row['rotation'] ?? 0));
        $design->setAnimation((string) ($row['animation'] ?? DesignInterface::ANIMATION_NONE));
        $design->setHoverEffect((string) ($row['hover_effect'] ?? DesignInterface::HOVER_EFFECT_NONE));
        $design->setSizes(is_array($row['sizes'] ?? null) ? $row['sizes'] : []);
        $design->setIsSystem(false);
        $design->setCustomCss($this->cssSanitizer->sanitize(isset($row['custom_css']) ? (string) $row['custom_css'] : null));
        $texts = [];
        foreach ((array) ($row['store_texts'] ?? []) as $code => $text) {
            $storeId = $this->scopeCodes->storeId((string) $code);
            if ($storeId === null) {
                $errors[] = sprintf('%s: this shop has no store view with code "%s".', $where, $code);
                continue;
            }
            $texts[$storeId] = [
                'text' => $text['text'] ?? null,
                'alt_text' => $text['alt_text'] ?? null,
                'tooltip' => $text['tooltip'] ?? null,
            ];
        }
        $design->setStoreTexts($texts);

        $image = null;
        if ($design->getType() === DesignInterface::TYPE_IMAGE) {
            $image = $this->prepareImage($where, $row['image'] ?? null, $errors);
            if ($image !== null) {
                $design->setImagePath('designs/' . $image['file']);
                $design->setImageWidth($image['width']);
                $design->setImageHeight($image['height']);
            }
        }
        foreach ($this->designValidator->validate($design) as $error) {
            $errors[] = sprintf('%s: %s', $where, $error);
        }

        return ['system' => false, 'design' => $design, 'image' => $image, 'updated' => $existing !== null];
    }

    /**
     * @param string $where
     * @param mixed $image
     * @param string[] $errors
     * @return array{file: string, contents: string, width: int|null, height: int|null}|null
     */
    private function prepareImage(string $where, mixed $image, array &$errors): ?array
    {
        if (!is_array($image) || !is_string($image['content'] ?? null) || !is_string($image['file'] ?? null)) {
            $errors[] = sprintf('%s: the image file is missing from the export.', $where);

            return null;
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- the image travels inside the JSON file
        $contents = base64_decode($image['content'], true);
        $name = substr($image['file'], (int) strrpos('/' . $image['file'], '/'));
        $file = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'image';
        if ($contents === false || $contents === '') {
            $errors[] = sprintf('%s: the image is not valid base64.', $where);

            return null;
        }
        $tmp = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $tmpFile = 'openlabel-import-' . bin2hex(random_bytes(8));
        $tmp->writeFile($tmpFile, $contents);
        try {
            $check = $this->imageValidator->validate($tmp->getAbsolutePath($tmpFile), $file);
        } finally {
            $tmp->delete($tmpFile);
        }
        foreach ($check->getErrors() as $error) {
            $errors[] = sprintf('%s: %s', $where, $error);
        }
        if ($check->getErrors() !== []) {
            return null;
        }

        return [
            'file' => $file,
            'contents' => $check->getSanitizedContents() ?? $contents,
            'width' => $check->getWidth(),
            'height' => $check->getHeight(),
        ];
    }

    /**
     * @param int $index
     * @param array<string, mixed> $row
     * @param array<string, array<string, mixed>> $designs
     * @param bool $replace
     * @param string[] $errors
     * @return array<string, mixed>
     */
    private function prepareLabel(int $index, array $row, array $designs, bool $replace, array &$errors): array
    {
        $where = sprintf('labels[%d] "%s"', $index, (string) $row['name']);
        $existing = $replace ? $this->findLabel((string) $row['name']) : null;
        $label = $this->labelFactory->create();
        if ($existing !== null) {
            $label->setLabelId((int) $existing->getLabelId());
        }
        $label->setName((string) $row['name']);
        $label->setStatus((int) ($row['status'] ?? LabelInterface::STATUS_ENABLED));
        $label->setPriority((int) ($row['priority'] ?? 0));
        $label->setStoreIds($this->ids((array) ($row['stores'] ?? []), 'store view', $where, $errors, true));
        $label->setCustomerGroupIds($this->ids((array) ($row['customer_groups'] ?? []), 'customer group', $where, $errors, false));
        $label->setValidFrom(isset($row['valid_from']) ? (string) $row['valid_from'] : null);
        $label->setValidTo(isset($row['valid_to']) ? (string) $row['valid_to'] : null);
        $label->setApplyToParent((bool) ($row['apply_to_parent'] ?? false));
        $label->setHideLowerPriority((bool) ($row['hide_lower_priority'] ?? false));
        $label->setHideOnZeroVariable((bool) ($row['hide_on_zero_variable'] ?? false));
        $label->setClickUrl(isset($row['click_url']) ? (string) $row['click_url'] : null);
        $label->setClickNofollow((bool) ($row['click_nofollow'] ?? false));
        $conditions = is_array($row['conditions'] ?? null) ? $row['conditions'] : [];
        foreach ($this->conditionGuard->errors($conditions) as $error) {
            $errors[] = sprintf('%s: %s', $where, $error);
        }
        $label->setConditionsSerialized($conditions === [] ? null : $this->json->serialize($conditions));
        $label->setDesignId(self::PENDING_DESIGN);

        $placements = [];
        foreach ((array) ($row['placements'] ?? []) as $p => $placementRow) {
            $placement = $this->placementFactory->create();
            $placement->setArea((string) ($placementRow['area'] ?? ''));
            $placement->setPosition((string) ($placementRow['position'] ?? ''));
            $placement->setPinPhysicalSide((bool) ($placementRow['pin_physical_side'] ?? false));
            $placement->setOffsetX((int) ($placementRow['offset_x'] ?? 0));
            $placement->setOffsetY((int) ($placementRow['offset_y'] ?? 0));
            $placement->setMaxLabels((int) ($placementRow['max_labels'] ?? 3));
            $placement->setStacking((string) ($placementRow['stacking'] ?? PlacementInterface::STACKING_VERTICAL));
            $placement->setGap((int) ($placementRow['gap'] ?? 4));
            $placement->setSortOrder((int) ($placementRow['sort_order'] ?? $p));
            $placements[] = ['placement' => $placement, 'design' => $placementRow['design'] ?? null];
        }
        $label->setPlacements(array_column($placements, 'placement'));
        foreach ($this->labelValidator->validate($label) as $error) {
            $errors[] = sprintf('%s: %s', $where, $error);
        }

        return [
            'label' => $label,
            'design' => (string) $row['design'],
            'placements' => $placements,
            'updated' => $existing !== null,
        ];
    }

    /**
     * @param array<int|string, mixed> $codes
     * @param string $what
     * @param string $where
     * @param string[] $errors
     * @param bool $stores
     * @return int[]
     */
    private function ids(array $codes, string $what, string $where, array &$errors, bool $stores): array
    {
        $ids = [];
        foreach ($codes as $code) {
            $id = $stores ? $this->scopeCodes->storeId((string) $code) : $this->scopeCodes->groupId((string) $code);
            if ($id === null) {
                $errors[] = sprintf('%s: this shop has no %s with code "%s".', $where, $what, (string) $code);
                continue;
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $prepared
     * @param string[] $written media files written so far, removed again on rollback
     * @return int
     */
    private function saveDesign(array $prepared, array &$written): int
    {
        if ($prepared['system']) {
            return (int) $prepared['id'];
        }
        /** @var DesignInterface $design */
        $design = $prepared['design'];
        if ($prepared['image'] !== null) {
            $path = $this->writeImage($prepared['image'], $written);
            $design->setImagePath($path);
        }

        return (int) $this->designRepository->save($design)->getDesignId();
    }

    /**
     * Writes the image under `openlabel/designs/`, reusing an identical file and never overwriting a different one.
     *
     * @param array{file: string, contents: string} $image
     * @param string[] $written
     * @return string image path relative to `openlabel/`
     */
    private function writeImage(array $image, array &$written): string
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $dot = strrpos($image['file'], '.');
        $base = $dot === false ? $image['file'] : substr($image['file'], 0, $dot);
        $extension = $dot === false ? '' : substr($image['file'], $dot);
        for ($n = 0;; $n++) {
            $name = $n === 0 ? $image['file'] : sprintf('%s-%d%s', $base, $n, $extension);
            $file = ImageUrl::MEDIA_DIR . '/designs/' . $name;
            if (!$media->isFile($file)) {
                $media->writeFile($file, $image['contents']);
                $written[] = $file;

                return 'designs/' . $name;
            }
            if ($media->readFile($file) === $image['contents']) {
                return 'designs/' . $name;
            }
        }
    }

    /**
     * @param array<string, mixed> $prepared
     * @param array<string, array<string, mixed>> $designs
     * @return int
     */
    private function saveLabel(array $prepared, array $designs): int
    {
        /** @var LabelInterface $label */
        $label = $prepared['label'];
        $label->setDesignId((int) $designs[$prepared['design']]['id']);
        foreach ($prepared['placements'] as $item) {
            /** @var PlacementInterface $placement */
            $placement = $item['placement'];
            $placement->setDesignId($item['design'] === null ? null : (int) $designs[(string) $item['design']]['id']);
        }

        return (int) $this->labelRepository->save($label)->getLabelId();
    }

    /**
     * @param string $name
     * @param bool $system
     * @return DesignInterface|null
     */
    private function findDesign(string $name, bool $system): ?DesignInterface
    {
        $this->searchCriteriaBuilder->addFilter(DesignInterface::NAME, $name);
        $this->searchCriteriaBuilder->addFilter(DesignInterface::IS_SYSTEM, $system ? 1 : 0);
        $items = $this->designRepository->getList($this->searchCriteriaBuilder->setPageSize(1)->create())->getItems();
        $design = reset($items);

        return $design === false ? null : $this->designRepository->getById((int) $design->getDesignId());
    }

    /**
     * @param string $name
     * @return LabelInterface|null
     */
    private function findLabel(string $name): ?LabelInterface
    {
        $this->searchCriteriaBuilder->addFilter(LabelInterface::NAME, $name);
        $items = $this->labelRepository->getList($this->searchCriteriaBuilder->setPageSize(1)->create())->getItems();
        $label = reset($items);

        return $label === false ? null : $label;
    }

    /**
     * @param array<string, array<string, mixed>> $designs
     * @param array<int, array<string, mixed>> $labels
     * @return array{designsCreated: int, designsUpdated: int, designsReused: int, labelsCreated: int, labelsUpdated: int}
     */
    private function count(array $designs, array $labels): array
    {
        $system = count(array_filter($designs, static fn (array $d): bool => $d['system']));
        $updated = count(array_filter($designs, static fn (array $d): bool => !$d['system'] && $d['updated']));
        $labelsUpdated = count(array_filter($labels, static fn (array $l): bool => $l['updated']));

        return [
            'designsCreated' => count($designs) - $system - $updated,
            'designsUpdated' => $updated,
            'designsReused' => $system,
            'labelsCreated' => count($labels) - $labelsUpdated,
            'labelsUpdated' => $labelsUpdated,
        ];
    }
}
