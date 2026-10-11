<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignInterfaceFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class DesignRepositoryTest extends TestCase
{
    private ?DesignRepositoryInterface $repository = null;
    private ?DesignInterfaceFactory $factory = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->repository = $om->get(DesignRepositoryInterface::class);
        $this->factory = $om->get(DesignInterfaceFactory::class);
    }

    public function testSaveStoresTextPerStoreViewAndFallsBackToDefault(): void
    {
        $design = $this->factory->create();
        $design->setName('Red pill')->setType(DesignInterface::TYPE_TEXT)->setBgColor('#e11d48')->setTextColor('#fff');
        $design->setText('Sale')->setText('Angebot', 1)->setAltText('Sale badge');

        $saved = $this->repository->save($design);
        $loaded = $this->repository->getById((int) $saved->getDesignId());

        self::assertSame('Red pill', $loaded->getName());
        self::assertSame('Sale', $loaded->getText());
        self::assertSame('Angebot', $loaded->getText(1));
        self::assertSame('Sale', $loaded->getText(99), 'unknown store falls back to the default text');
        self::assertSame('Sale badge', $loaded->getAltText(1), 'alt text falls back to the default');
        self::assertNull($loaded->getTooltip());
    }

    public function testSaveStoresAnimationHoverEffectAndBreakpointSizes(): void
    {
        $design = $this->factory->create();
        $design->setName('Animated')->setType(DesignInterface::TYPE_TEXT)->setBgColor('#e11d48')->setTextColor('#ffffff')
            ->setAnimation('pop')->setHoverEffect('grow')
            ->setSizes(['sm' => ['width' => 24, 'font_size' => 11], 'lg' => ['width' => 12]]);
        $design->setText('Sale');

        $loaded = $this->repository->getById((int) $this->repository->save($design)->getDesignId());

        self::assertSame('pop', $loaded->getAnimation());
        self::assertSame('grow', $loaded->getHoverEffect());
        self::assertSame(['sm' => ['width' => 24, 'font_size' => 11], 'lg' => ['width' => 12]], $loaded->getSizes());
    }

    public function testInvalidAnimationIsRefused(): void
    {
        $design = $this->factory->create();
        $design->setName('Spinning')->setType(DesignInterface::TYPE_TEXT)->setAnimation('spin');
        $design->setText('Sale');

        $this->expectException(\Magento\Framework\Validation\ValidationException::class);
        $this->repository->save($design);
    }

    #[DataFixture(DesignFixture::class, ['store_texts' => [0 => ['text' => 'Sale'], 1 => ['text' => 'Angebot']]], 'design')]
    public function testResaveReplacesStoreTexts(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $design = $this->repository->getById($id);
        $design->setText(null, 1);

        $this->repository->save($design);

        $reloaded = $this->repository->getById($id);
        self::assertSame('Sale', $reloaded->getText(1));
        self::assertArrayNotHasKey(1, $reloaded->getStoreTexts());
    }

    #[DataFixture(DesignFixture::class, ['name' => 'Text one'], 'd1')]
    #[DataFixture(DesignFixture::class, ['name' => 'Image one', 'type' => 'image', 'image_path' => 'designs/a.svg', 'store_texts' => [0 => ['alt_text' => 'A']]], 'd2')]
    public function testGetListFiltersByType(): void
    {
        $criteria = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class)
            ->addFilter(DesignInterface::TYPE, DesignInterface::TYPE_IMAGE)
            ->create();

        $result = $this->repository->getList($criteria);

        self::assertSame(1, $result->getTotalCount());
        $items = $result->getItems();
        self::assertSame('Image one', reset($items)->getName());
        self::assertSame('A', reset($items)->getAltText(), 'store texts are loaded for list items');
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testDeleteRemovesStoreRows(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();

        self::assertTrue($this->repository->deleteById($id));

        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $count = $resource->getConnection()->fetchOne(
            $resource->getConnection()->select()->from($resource->getTableName('openlabel_design_store'), 'COUNT(*)')
                ->where('design_id = ?', $id)
        );
        self::assertSame(0, (int) $count);
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById($id);
    }

    public function testGetByIdThrowsForUnknownDesign(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Design with ID "999999" does not exist.');

        $this->repository->getById(999999);
    }

    public function testSaveRejectsInvalidDesignWithActionableMessages(): void
    {
        $design = $this->factory->create();
        $design->setName('No text')->setType(DesignInterface::TYPE_TEXT)->setBgColor('red');

        try {
            $this->repository->save($design);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            $messages = array_map(static fn (\Throwable $error): string => $error->getMessage(), $e->getErrors());
            self::assertContains('Enter the label text for the default store view.', $messages);
            self::assertContains('Colours must be hex values such as #e11d48.', $messages);
            self::assertNull($design->getDesignId(), 'nothing is persisted on validation failure');
        }
    }

    #[DataFixture(DesignFixture::class, [], 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$'], 'label')]
    public function testDeleteRefusesADesignThatLabelsStillUse(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('used by 1 label');

        $this->repository->deleteById($id);
    }
}
