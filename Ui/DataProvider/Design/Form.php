<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Ui\DataProvider\Design;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Controller\Adminhtml\Design\Save;
use Iranimij\OpenLabel\Model\Config;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\ResourceModel\Design\CollectionFactory;
use Iranimij\OpenLabel\Model\Variable\Help;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data and dynamic meta for the design form: one text/alt row per store view with "Use default", the variable
 * picker list, the locked notice for built-in designs and the custom CSS fieldset only for its ACL resource.
 */
class Form extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DesignRepositoryInterface $designRepository
     * @param StoreManagerInterface $storeManager
     * @param AuthorizationInterface $authorization
     * @param ImageUrl $imageUrl
     * @param Help $variableHelp
     * @param DataPersistorInterface $dataPersistor
     * @param Config $config
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly AuthorizationInterface $authorization,
        private readonly ImageUrl $imageUrl,
        private readonly Help $variableHelp,
        private readonly DataPersistorInterface $dataPersistor,
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
        $result = [];
        foreach ($this->collection->getAllIds() as $id) {
            $result[(int) $id] = $this->designData($this->designRepository->getById((int) $id));
        }
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (is_array($persisted)) {
            $id = (int) ($persisted[DesignInterface::DESIGN_ID] ?? 0);
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
        $meta['custom_css']['arguments']['data']['config']['visible']
            = $this->authorization->isAllowed('Iranimij_OpenLabel::custom_css');
        // The browser checks the size first: it must allow what the "Largest image upload" setting allows.
        $meta['image']['children']['image']['arguments']['data']['config']['maxFileSize']
            = $this->config->getMaxUploadKb() * 1024;
        $meta['text']['children']['store_texts_text']['arguments']['data']['config']['variables']
            = array_values($this->variableHelp->getVariables());

        $stores = [];
        $sort = 10;
        foreach ($this->storeManager->getStores() as $store) {
            $id = (int) $store->getId();
            $stores['store_' . $id] = $this->storeFieldset($id, (string) $store->getName(), $sort += 10);
        }
        $meta['store_views']['children'] = $stores;
        $meta['store_views']['arguments']['data']['config']['visible'] = $stores !== [];

        return $meta;
    }

    /**
     * @param DesignInterface $design
     * @return array<string, mixed>
     */
    private function designData(DesignInterface $design): array
    {
        /** @var \Iranimij\OpenLabel\Model\Design $design */
        $data = $design->getData();
        unset($data[DesignInterface::STORE_TEXTS]);
        $data[DesignInterface::SIZES] = $design->getSizes();
        $data[DesignInterface::ANIMATION] = $design->getAnimation();
        $data[DesignInterface::HOVER_EFFECT] = $design->getHoverEffect();
        $texts = $design->getStoreTexts();
        $data['store_texts'] = [];
        $data['store_texts'][0] = [
            'text' => $texts[0]['text'] ?? null,
            'alt_text' => $texts[0]['alt_text'] ?? null,
            'tooltip' => $texts[0]['tooltip'] ?? null,
        ];
        foreach ($this->storeManager->getStores() as $store) {
            $id = (int) $store->getId();
            $data['store_texts'][$id] = [
                'text' => $texts[$id]['text'] ?? null,
                'alt_text' => $texts[$id]['alt_text'] ?? null,
                'tooltip' => $texts[$id]['tooltip'] ?? null,
                'use_default' => isset($texts[$id]) ? '0' : '1',
            ];
        }
        if ($design->getImagePath() !== null) {
            $data['image'] = [[
                'file' => $design->getImagePath(),
                'name' => substr((string) strrchr('/' . $design->getImagePath(), '/'), 1),
                'url' => $this->imageUrl->get($design->getImagePath()),
                'width' => $design->getImageWidth(),
                'height' => $design->getImageHeight(),
                'type' => 'image',
            ]];
        }
        $data['is_system'] = $design->isSystem() ? '1' : '0';
        $data['is_system_flag'] = $design->isSystem();

        return $data;
    }

    /**
     * @param int $storeId
     * @param string $storeName
     * @param int $sortOrder
     * @return array<string, mixed>
     */
    private function storeFieldset(int $storeId, string $storeName, int $sortOrder): array
    {
        $prefix = 'store_texts.' . $storeId . '.';
        $field = static fn (string $label, string $scope, string $element, int $sort, array $extra = []): array => [
            'arguments' => ['data' => ['config' => array_merge([
                'componentType' => 'field',
                'formElement' => $element,
                'dataType' => $element === 'checkbox' ? 'boolean' : 'text',
                'label' => $label,
                'dataScope' => $prefix . $scope,
                'sortOrder' => $sort,
            ], $extra)]],
        ];
        $disabledByDefault = ['imports' => ['disabled' => '${ $.provider }:data.store_texts.' . $storeId . '.use_default']];

        return [
            'arguments' => ['data' => ['config' => [
                'componentType' => 'fieldset',
                'label' => $storeName,
                'collapsible' => false,
                'sortOrder' => $sortOrder,
            ]]],
            'children' => [
                'use_default' => $field((string) __('Use the default text'), 'use_default', 'checkbox', 10, [
                    'prefer' => 'toggle',
                    'valueMap' => ['true' => '1', 'false' => '0'],
                    'default' => '1',
                ]),
                'text' => $field((string) __('Text'), 'text', 'input', 20, $disabledByDefault + [
                    'notice' => (string) __('Shown in this store view instead of the default text.'),
                ]),
                'alt_text' => $field((string) __('Alternative text'), 'alt_text', 'input', 30, $disabledByDefault),
                'tooltip' => $field((string) __('Tooltip'), 'tooltip', 'input', 40, $disabledByDefault),
            ],
        ];
    }
}
