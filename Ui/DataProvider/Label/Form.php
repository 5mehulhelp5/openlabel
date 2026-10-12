<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Ui\DataProvider\Label;

use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Controller\Adminhtml\Label\Save;
use Iranimij\OpenLabel\Model\Config;
use Iranimij\OpenLabel\Model\Design\PickerOptions;
use Iranimij\OpenLabel\Model\Label\DateConverter;
use Iranimij\OpenLabel\Model\Label\FormMapper;
use Iranimij\OpenLabel\Model\Label\QuickConditions;
use Iranimij\OpenLabel\Model\Source\CustomerGroups;
use Iranimij\OpenLabel\Model\Variable\Help;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Iranimij\OpenLabel\Model\ResourceModel\Label\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data and dynamic meta for the label form: dates in the shop timezone, "All Store Views" for no restriction,
 * placement rows, the design picker list and, for a new label, one listing placement to start from.
 */
class Form extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param LabelRepositoryInterface $labelRepository
     * @param DateConverter $dateConverter
     * @param PickerOptions $pickerOptions
     * @param TimezoneInterface $timezone
     * @param UrlInterface $urlBuilder
     * @param DataPersistorInterface $dataPersistor
     * @param QuickConditions $quickConditions
     * @param Help $variableHelp
     * @param CustomerGroups $customerGroups
     * @param AssetRepository $assetRepository
     * @param Config $config
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly DateConverter $dateConverter,
        private readonly PickerOptions $pickerOptions,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly QuickConditions $quickConditions,
        private readonly Help $variableHelp,
        private readonly CustomerGroups $customerGroups,
        private readonly AssetRepository $assetRepository,
        private readonly Config $config,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData()
    {
        $result = ['' => $this->defaults()];
        foreach ($this->collection->getAllIds() as $id) {
            $result[(int) $id] = $this->labelData($this->labelRepository->getById((int) $id));
        }
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (is_array($persisted)) {
            $id = (int) ($persisted['label_id'] ?? 0);
            $result[$id > 0 ? $id : ''] = $persisted;
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        $designs = $this->pickerOptions->get();
        $samples = [];
        foreach ($this->variableHelp->getVariables() as $code => $variable) {
            $samples[$code] = $variable['sample'];
        }
        $meta['basics']['children']['live_preview']['arguments']['data']['config'] = [
            'designs' => $designs,
            'samples' => $samples,
            'groups' => $this->customerGroups->toOptionArray(),
            'sampleImage' => $this->assetRepository->getUrl('Iranimij_OpenLabel::images/sample-product.svg'),
        ];
        $meta['basics']['children']['design_id']['arguments']['data']['config'] = [
            'designs' => $designs,
            'createUrl' => $this->urlBuilder->getUrl('openlabel/design/new'),
            'refreshUrl' => $this->urlBuilder->getUrl('openlabel/label/designOptions'),
        ];
        $zone = $this->timezone->getConfigTimezone();
        $hints = [
            'valid_from' => __('Leave empty to start right away.'),
            'valid_to' => __('Leave empty to run until you switch it off.'),
        ];
        foreach ($hints as $field => $hint) {
            $meta['who_when']['children'][$field]['arguments']['data']['config']['notice']
                = (string) __('%1 Times are in the shop timezone (%2).', $hint, $zone);
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            LabelInterface::STATUS => '1',
            LabelInterface::PRIORITY => '0',
            LabelInterface::STORE_IDS => ['0'],
            LabelInterface::CUSTOMER_GROUP_IDS => [],
            LabelInterface::APPLY_TO_PARENT => '1',
            LabelInterface::HIDE_LOWER_PRIORITY => '0',
            LabelInterface::HIDE_ON_ZERO_VARIABLE => '0',
            'quick' => $this->quickConditions->decompose(null)[0],
            LabelInterface::PLACEMENTS => [[
                PlacementInterface::AREA => PlacementInterface::AREA_LISTING,
                PlacementInterface::POSITION => 'tl',
                PlacementInterface::MAX_LABELS => (string) $this->config->getDefaultMaxLabels(),
                PlacementInterface::STACKING => PlacementInterface::STACKING_VERTICAL,
                PlacementInterface::GAP => (string) FormMapper::DEFAULT_GAP,
                PlacementInterface::OFFSET_X => '0',
                PlacementInterface::OFFSET_Y => '0',
                'record_id' => '0',
            ]],
        ];
    }

    /**
     * @param LabelInterface $label
     * @return array<string, mixed>
     */
    private function labelData(LabelInterface $label): array
    {
        $placements = [];
        foreach ($label->getPlacements() as $i => $placement) {
            $placements[] = [
                PlacementInterface::AREA => $placement->getArea(),
                PlacementInterface::POSITION => $placement->getPosition(),
                PlacementInterface::DESIGN_ID => $placement->getDesignId() === null ? '' : (string) $placement->getDesignId(),
                PlacementInterface::PIN_PHYSICAL_SIDE => $placement->isPinPhysicalSide() ? '1' : '0',
                PlacementInterface::OFFSET_X => (string) $placement->getOffsetX(),
                PlacementInterface::OFFSET_Y => (string) $placement->getOffsetY(),
                PlacementInterface::MAX_LABELS => (string) $placement->getMaxLabels(),
                PlacementInterface::STACKING => $placement->getStacking(),
                PlacementInterface::GAP => (string) $placement->getGap(),
                'record_id' => (string) $i,
            ];
        }
        $stores = array_map('strval', $label->getStoreIds());

        return [
            LabelInterface::LABEL_ID => (string) $label->getLabelId(),
            LabelInterface::NAME => $label->getName(),
            LabelInterface::STATUS => (string) $label->getStatus(),
            LabelInterface::PRIORITY => (string) $label->getPriority(),
            LabelInterface::DESIGN_ID => (string) $label->getDesignId(),
            LabelInterface::APPLY_TO_PARENT => $label->isApplyToParent() ? '1' : '0',
            LabelInterface::HIDE_LOWER_PRIORITY => $label->isHideLowerPriority() ? '1' : '0',
            LabelInterface::HIDE_ON_ZERO_VARIABLE => $label->isHideOnZeroVariable() ? '1' : '0',
            LabelInterface::STORE_IDS => $stores === [] ? ['0'] : $stores,
            LabelInterface::CUSTOMER_GROUP_IDS => array_map('strval', $label->getCustomerGroupIds()),
            LabelInterface::VALID_FROM => $this->dateConverter->toLocal($label->getValidFrom()),
            LabelInterface::VALID_TO => $this->dateConverter->toLocal($label->getValidTo()),
            'quick' => $this->quickConditions->decompose($label->getConditionsSerialized())[0],
            LabelInterface::PLACEMENTS => $placements,
        ];
    }
}
