<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration;

use Iranimij\OpenLabel\Block\Adminhtml\System\Config\IndexMode;
use Iranimij\OpenLabel\Model\Config;
use Iranimij\OpenLabel\Ui\DataProvider\Label\Form;
use Magento\Config\Model\Config\Structure\Data as ConfigStructureData;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class ConfigSectionTest extends TestCase
{
    public function testOpenLabelSectionLivesUnderTheIranimijTab(): void
    {
        $data = Bootstrap::getObjectManager()->get(ConfigStructureData::class)->get();

        self::assertArrayHasKey('openlabel', $data['sections']);
        self::assertSame('iranimij', $data['sections']['openlabel']['tab']);
        self::assertSame('Iranimij_OpenLabel::config', $data['sections']['openlabel']['resource']);
        self::assertArrayHasKey('enabled', $data['sections']['openlabel']['children']['general']['children']);
    }

    public function testModuleIsEnabledByDefault(): void
    {
        $scopeConfig = Bootstrap::getObjectManager()->get(ScopeConfigInterface::class);

        self::assertTrue($scopeConfig->isSetFlag('openlabel/general/enabled'));
    }

    public function testAdminFieldsAndTheirDefaults(): void
    {
        $om = Bootstrap::getObjectManager();
        $fields = $om->get(ConfigStructureData::class)->get()['sections']['openlabel']['children']['general']['children'];

        foreach (['enabled', 'default_max_labels', 'index_mode', 'debug'] as $field) {
            self::assertArrayHasKey($field, $fields, "field $field");
        }
        $config = $om->get(Config::class);
        self::assertSame(3, $config->getDefaultMaxLabels());
        self::assertFalse($config->isDebug());
    }

    public function testBrandVariableReadsTheManufacturerAttributeByDefault(): void
    {
        $om = Bootstrap::getObjectManager();
        $fields = $om->get(ConfigStructureData::class)->get()['sections']['openlabel']['children']['variables']['children'];

        self::assertArrayHasKey('brand_attribute', $fields);
        self::assertSame('manufacturer', $om->get(ScopeConfigInterface::class)->getValue('openlabel/variables/brand_attribute', 'store'));
    }

    #[ConfigFixture('openlabel/general/default_max_labels', '25', 'default')]
    public function testDefaultMaxLabelsIsClampedToTheAllowedRange(): void
    {
        self::assertSame(10, Bootstrap::getObjectManager()->get(Config::class)->getDefaultMaxLabels());
    }

    #[ConfigFixture('openlabel/general/default_max_labels', '2', 'default')]
    public function testNewLabelStartsWithTheConfiguredMaxLabels(): void
    {
        $provider = Bootstrap::getObjectManager()->create(Form::class, [
            'name' => 'openlabel_label_form_data_source', 'primaryFieldName' => 'label_id', 'requestFieldName' => 'id',
        ]);

        self::assertSame('2', $provider->getData()['']['placements'][0]['max_labels']);
    }

    public function testIndexModeHintNamesTheCurrentModeAndRecommendsSchedule(): void
    {
        $om = Bootstrap::getObjectManager();
        $indexer = $om->get(IndexerRegistry::class)->get('openlabel_product');
        $scheduled = $indexer->isScheduled();
        $element = $om->create(Text::class);
        $element->setForm($om->create(\Magento\Framework\Data\Form::class));
        try {
            $indexer->setScheduled(false);
            $html = $om->get(LayoutInterface::class)->createBlock(IndexMode::class)->render($element);
            self::assertStringContainsString('Update on Save', $html);
            self::assertStringContainsString('Update by Schedule', $html, 'the recommendation');
        } finally {
            $indexer->setScheduled($scheduled);
        }
    }
}
