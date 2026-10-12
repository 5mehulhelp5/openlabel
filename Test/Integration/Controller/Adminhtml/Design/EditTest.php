<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Controller\Adminhtml\Design;

use Iranimij\OpenLabel\Model\ResourceModel\Design\CollectionFactory;
use Iranimij\OpenLabel\Ui\DataProvider\Design\Form;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Magento\Framework\Acl\Builder;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class EditTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Iranimij_OpenLabel::designs';

    /**
     * @var string
     */
    protected $uri = 'backend/openlabel/design/new';

    public function testNewDesignFormRenders(): void
    {
        $this->dispatch($this->uri);

        self::assertStringContainsString('openlabel_design_form', (string) $this->getResponse()->getBody());
    }

    public function testBuiltInDesignShowsTheDuplicateButtonAndNoSave(): void
    {
        $id = (int) $this->_objectManager->create(CollectionFactory::class)->create()
            ->addFieldToFilter('is_system', ['eq' => 1])->getFirstItem()->getId();

        $this->dispatch('backend/openlabel/design/edit/id/' . $id);

        $body = (string) $this->getResponse()->getBody();
        self::assertStringContainsString('Duplicate to edit', $body);
        self::assertStringNotContainsString('save_and_continue', $body);
    }

    #[DataFixture(DesignFixture::class, ['name' => 'Own', 'store_texts' => [0 => ['text' => 'Sale'], 1 => ['text' => 'Angebot']]], 'design')]
    public function testDataProviderLoadsStoreTextsAndHidesCustomCssWithoutAcl(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $this->_objectManager->get(Builder::class)->getAcl()->deny($this->_auth->getUser()->getRoles(), 'Iranimij_OpenLabel::custom_css');

        $provider = $this->_objectManager->create(Form::class, [
            'name' => 'openlabel_design_form_data_source',
            'primaryFieldName' => 'design_id',
            'requestFieldName' => 'id',
        ]);
        $data = $provider->getData()[$id];

        self::assertSame('Sale', $data['store_texts'][0]['text']);
        self::assertSame('Angebot', $data['store_texts'][1]['text']);
        self::assertSame('0', (string) $data['store_texts'][1]['use_default']);
        self::assertFalse($provider->getMeta()['custom_css']['arguments']['data']['config']['visible']);
    }

    #[ConfigFixture('openlabel/images/max_upload_kb', '3072', 'default')]
    public function testTheImageUploaderAllowsTheConfiguredSize(): void
    {
        $provider = $this->_objectManager->create(Form::class, [
            'name' => 'openlabel_design_form_data_source',
            'primaryFieldName' => 'design_id',
            'requestFieldName' => 'id',
        ]);

        self::assertSame(
            3 * 1024 * 1024,
            $provider->getMeta()['image']['children']['image']['arguments']['data']['config']['maxFileSize'] ?? null,
            'the browser must not refuse what the "Largest image upload" setting allows'
        );
    }
}
