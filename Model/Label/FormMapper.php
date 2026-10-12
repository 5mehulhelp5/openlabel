<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Copies the label form's POST data onto a label: basics, scope, schedule (shop timezone → UTC) and placements.
 * Shared by the Save and Validate controllers so both see exactly the same label.
 */
class FormMapper
{
    public const DEFAULT_MAX_LABELS = 3;
    public const DEFAULT_GAP = 4;

    /**
     * @param DateConverter $dateConverter
     * @param PlacementInterfaceFactory $placementFactory
     * @param QuickConditions $quickConditions
     * @param RuleTree $ruleTree
     */
    public function __construct(
        private readonly DateConverter $dateConverter,
        private readonly PlacementInterfaceFactory $placementFactory,
        private readonly QuickConditions $quickConditions,
        private readonly RuleTree $ruleTree
    ) {
    }

    /**
     * @param LabelInterface $label
     * @param array<string, mixed> $data
     * @return void
     * @throws LocalizedException when a date cannot be read
     */
    public function apply(LabelInterface $label, array $data): void
    {
        $label->setName(trim((string) ($data[LabelInterface::NAME] ?? '')))
            ->setStatus((int) ($data[LabelInterface::STATUS] ?? 0) === LabelInterface::STATUS_ENABLED ? 1 : 0)
            ->setPriority(max(0, (int) ($data[LabelInterface::PRIORITY] ?? 0)))
            ->setApplyToParent($this->flag($data[LabelInterface::APPLY_TO_PARENT] ?? false))
            ->setHideLowerPriority($this->flag($data[LabelInterface::HIDE_LOWER_PRIORITY] ?? false))
            ->setHideOnZeroVariable($this->flag($data[LabelInterface::HIDE_ON_ZERO_VARIABLE] ?? false))
            ->setClickUrl(trim((string) ($data[LabelInterface::CLICK_URL] ?? '')))
            ->setClickNofollow($this->flag($data[LabelInterface::CLICK_NOFOLLOW] ?? false));
        $designId = (int) ($data[LabelInterface::DESIGN_ID] ?? 0);
        if ($designId > 0) {
            $label->setDesignId($designId);
        }

        $stores = $this->ids($data[LabelInterface::STORE_IDS] ?? []);
        $label->setStoreIds(in_array(0, $stores, true) ? [] : $stores);
        $label->setCustomerGroupIds($this->ids($data[LabelInterface::CUSTOMER_GROUP_IDS] ?? []));

        $label->setValidFrom($this->dateConverter->toUtc((string) ($data[LabelInterface::VALID_FROM] ?? '')));
        $label->setValidTo($this->dateConverter->toUtc((string) ($data[LabelInterface::VALID_TO] ?? ''), true));

        if (array_key_exists(LabelInterface::PLACEMENTS, $data)) {
            $label->setPlacements($this->placements($data[LabelInterface::PLACEMENTS]));
        }
        $this->applyConditions($label, $data);
    }

    /**
     * "Show when": quick toggles and the advanced tree. Data without either (grid inline edit) leaves the stored
     * conditions alone; toggles without a tree keep the stored advanced tree.
     *
     * @param LabelInterface $label
     * @param array<string, mixed> $data
     * @return void
     */
    private function applyConditions(LabelInterface $label, array $data): void
    {
        $hasQuick = is_array($data['quick'] ?? null);
        $hasRule = is_array($data['rule'] ?? null);
        if (!$hasQuick && !$hasRule) {
            return;
        }
        [$storedQuick, $storedAdvanced] = $this->quickConditions->decompose($label->getConditionsSerialized());
        $quick = $hasQuick ? $data['quick'] : $storedQuick;
        $advanced = $hasRule ? $this->ruleTree->fromPost($data['rule']) : $storedAdvanced;
        $label->setConditionsSerialized($this->quickConditions->compose($quick, $advanced));
    }

    /**
     * @param mixed $rows dynamic-rows data
     * @return PlacementInterface[]
     */
    private function placements(mixed $rows): array
    {
        $placements = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || $this->flag($row['delete'] ?? false)) {
                continue;
            }
            $designId = (int) ($row[PlacementInterface::DESIGN_ID] ?? 0);
            $area = (string) ($row[PlacementInterface::AREA] ?? PlacementInterface::AREA_LISTING);
            // Item lists show the labels as one row with the item name: the picker's position does not apply.
            $position = in_array($area, PlacementInterface::ITEM_AREAS, true)
                ? 'under_name'
                : (string) ($row[PlacementInterface::POSITION] ?? 'tl');
            $placement = $this->placementFactory->create();
            $placement->setArea($area)
                ->setPosition($position)
                ->setDesignId($designId > 0 ? $designId : null)
                ->setPinPhysicalSide($this->flag($row[PlacementInterface::PIN_PHYSICAL_SIDE] ?? false))
                ->setOffsetX((int) ($row[PlacementInterface::OFFSET_X] ?? 0))
                ->setOffsetY((int) ($row[PlacementInterface::OFFSET_Y] ?? 0))
                ->setMaxLabels($this->int($row[PlacementInterface::MAX_LABELS] ?? null, self::DEFAULT_MAX_LABELS))
                ->setStacking((string) ($row[PlacementInterface::STACKING] ?? '') ?: PlacementInterface::STACKING_VERTICAL)
                ->setGap($this->int($row[PlacementInterface::GAP] ?? null, self::DEFAULT_GAP));
            $placements[] = $placement;
        }

        return $placements;
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private function ids(mixed $value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }
        $ids = [];
        foreach (is_array($value) ? $value : [] as $id) {
            if ($id !== '' && $id !== null && is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function flag(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * @param mixed $value
     * @param int $default
     * @return int
     */
    private function int(mixed $value, int $default): int
    {
        return $value === null || trim((string) $value) === '' ? $default : (int) $value;
    }
}
