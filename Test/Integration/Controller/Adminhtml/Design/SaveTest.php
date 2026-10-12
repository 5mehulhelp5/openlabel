<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Controller\Adminhtml\Design;

use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\ResourceModel\Design\CollectionFactory;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Magento\Framework\Acl\Builder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class SaveTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Iranimij_OpenLabel::designs';

    /**
     * @var string
     */
    protected $uri = 'backend/openlabel/design/save';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    public function testNewTextDesignIsSavedWithStoreTextsAndSanitizedCustomCss(): void
    {
        $this->post($this->textDesign('Campaign red') + [
            'custom_css' => '.ol-d-x{letter-spacing:1px}.y{width:expression(alert(1));color:red}',
            'back' => 'edit',
        ]);

        $design = $this->designNamed('Campaign red');
        self::assertSame('Sale', $design->getText());
        self::assertSame('Angebot', $design->getText(1));
        self::assertSame('#b91c1c', $design->getBgColor());
        self::assertStringContainsString('letter-spacing', (string) $design->getCustomCss());
        self::assertStringNotContainsString('expression', (string) $design->getCustomCss());
        self::assertRedirect(self::stringContains('openlabel/design/edit/id/' . $design->getDesignId()));
    }

    public function testMotionAndBreakpointSizesAreSaved(): void
    {
        $this->post($this->textDesign('Moving') + [
            'animation' => 'pop',
            'hover_effect' => 'grow',
            'sizes' => ['sm' => ['width' => '26', 'font_size' => '11'], 'md' => ['width' => '', 'font_size' => ''], 'lg' => ['width' => '14']],
        ]);

        $design = $this->designNamed('Moving');
        self::assertSame('pop', $design->getAnimation());
        self::assertSame('grow', $design->getHoverEffect());
        self::assertSame(['sm' => ['width' => 26, 'font_size' => 11], 'lg' => ['width' => 14]], $design->getSizes(), 'empty breakpoints are not stored');
    }

    public function testStoreViewUsingTheDefaultTextStoresNoRow(): void
    {
        $data = $this->textDesign('Default only');
        $data['store_texts'][1]['use_default'] = '1';

        $this->post($data);

        self::assertArrayNotHasKey(1, $this->designNamed('Default only')->getStoreTexts());
    }

    #[DataFixture(DesignFixture::class, ['name' => 'Has CSS', 'custom_css' => '.keep{color:blue}'], 'design')]
    public function testCustomCssIsIgnoredWithoutItsAclResource(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $this->_objectManager->get(Builder::class)->getAcl()->deny($this->_auth->getUser()->getRoles(), 'Iranimij_OpenLabel::custom_css');

        $this->post(['design_id' => (string) $id] + $this->textDesign('Has CSS') + ['custom_css' => '.evil{}']);

        self::assertSame('.keep{color:blue}', $this->repository()->getById($id)->getCustomCss());
    }

    public function testImageDesignStoresPathAndDimensions(): void
    {
        $this->post([
            'name' => 'Image badge', 'type' => 'image', 'size_mode' => 'percent', 'width' => '18', 'opacity' => '100',
            'image' => [['file' => 'designs/badge.svg', 'name' => 'badge.svg', 'width' => '80', 'height' => '24']],
            'store_texts' => [0 => ['text' => '', 'alt_text' => 'Sale badge']],
        ]);

        $design = $this->designNamed('Image badge');
        self::assertSame('designs/badge.svg', $design->getImagePath());
        self::assertSame(80, $design->getImageWidth());
        self::assertSame(24, $design->getImageHeight());
        self::assertSame('Sale badge', $design->getAltText());
    }

    public function testBuiltInDesignCannotBeChanged(): void
    {
        $system = $this->_objectManager->create(CollectionFactory::class)->create()
            ->addFieldToFilter('is_system', ['eq' => 1])->getFirstItem();
        $id = (int) $system->getId();

        $this->post(['design_id' => (string) $id] + $this->textDesign('Hijacked'));

        self::assertSame((string) $system->getData('name'), $this->repository()->getById($id)->getName());
        $this->assertSessionMessages(
            self::containsEqual('Built-in designs are locked. Use “Duplicate to edit” to make your own version.'),
            MessageInterface::TYPE_ERROR
        );
    }

    public function testValidationErrorKeepsTheMerchantOnTheForm(): void
    {
        $data = $this->textDesign('Bad colour');
        $data['bg_color'] = 'red';

        $this->post($data);

        $this->assertSessionMessages(
            self::containsEqual('Colours must be hex values such as #e11d48.'),
            MessageInterface::TYPE_ERROR
        );
        self::assertRedirect(self::stringContains('openlabel/design/new'));
    }

    /**
     * @return array<string, mixed>
     */
    private function textDesign(string $name): array
    {
        return [
            'name' => $name, 'type' => 'shape', 'shape' => 'pill', 'bg_color' => '#b91c1c', 'text_color' => '#ffffff',
            'border_color' => '', 'border_width' => '0', 'font_size' => '14', 'size_mode' => 'percent',
            'width' => '18', 'height' => '', 'opacity' => '100', 'rotation' => '0',
            'store_texts' => [
                0 => ['text' => 'Sale', 'alt_text' => ''],
                1 => ['text' => 'Angebot', 'alt_text' => '', 'use_default' => '0'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(array $data): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue($data);
        $this->dispatch($this->uri);
    }

    private function designNamed(string $name): \Iranimij\OpenLabel\Api\Data\DesignInterface
    {
        $id = (int) $this->_objectManager->create(CollectionFactory::class)->create()
            ->addFieldToFilter('name', ['eq' => $name])->getFirstItem()->getId();
        self::assertGreaterThan(0, $id, "design $name was saved");

        return $this->repository()->getById($id);
    }

    private function repository(): DesignRepositoryInterface
    {
        return $this->_objectManager->create(DesignRepositoryInterface::class);
    }
}
