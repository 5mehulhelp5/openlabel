<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\Css;

use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\Config\Backend\GlobalCss;
use Iranimij\OpenLabel\Model\Css\Regenerator;
use Iranimij\OpenLabel\Model\Css\Storage;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class StylesheetTest extends TestCase
{
    private ?Regenerator $regenerator = null;
    private ?Storage $storage = null;
    private ?WriteInterface $media = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->regenerator = $om->get(Regenerator::class);
        $this->storage = $om->get(Storage::class);
        $this->media = $om->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA);
        $this->media->delete(Storage::DIRECTORY);
        $om->get(CacheInterface::class)->remove(Storage::CACHE_KEY_PREFIX . '1');
    }

    #[DataFixture(DesignFixture::class, ['bg_color' => '#123456'], 'design')]
    public function testRegenerateWritesOneHashedFilePerStoreView(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();

        $paths = $this->regenerator->regenerateAll();

        self::assertArrayHasKey(1, $paths);
        self::assertMatchesRegularExpression('#^openlabel/1/openlabel\.[0-9a-f]{12}\.css$#', $paths[1]);
        self::assertSame($paths[1], $this->storage->current(1));
        $css = $this->media->readFile($paths[1]);
        self::assertStringContainsString('.ol-d-' . $id . '{--ol-bg:#123456', $css);
        self::assertStringContainsString('.ol-stack{', $css, 'structural CSS is part of the same file (FE2)');
        self::assertStringContainsString('/media/openlabel/1/openlabel.', $this->storage->url(1, $paths[1]));
    }

    #[ConfigFixture('openlabel_css/general/global_css', '.ol-label{letter-spacing:.04em}', 'default')]
    public function testTheGlobalCssIsPartOfEveryStoresStylesheet(): void
    {
        $paths = $this->regenerator->regenerateAll();

        self::assertStringEndsWith("/* global */\n.ol-label{letter-spacing:.04em}\n", $this->media->readFile($paths[1]));
    }

    #[AppArea('adminhtml')]
    public function testSavingTheGlobalCssInTheAdminPutsItInTheStylesheet(): void
    {
        /** @var Value $value */
        $value = Bootstrap::getObjectManager()->create(GlobalCss::class);
        $value->setData(['path' => 'openlabel_css/general/global_css', 'scope' => 'default', 'scope_id' => 0])
            ->setValue('.ol-label{word-spacing:2px}')
            ->save();

        $current = (string) $this->storage->current(1);
        self::assertStringEndsWith("/* global */\n.ol-label{word-spacing:2px}\n", $this->media->readFile($current), 'the value just saved, not the cached config');
    }

    #[DataFixture(DesignFixture::class, ['bg_color' => '#123456'], 'design')]
    public function testDesignSaveRegeneratesAndKeepsThePreviousFileForCachedPages(): void
    {
        $before = $this->regenerator->regenerateAll()[1];
        $repository = Bootstrap::getObjectManager()->get(DesignRepositoryInterface::class);
        $design = $repository->getById((int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId());

        $repository->save($design->setBgColor('#654321'));

        $after = $this->storage->current(1);
        self::assertNotSame($before, $after);
        self::assertStringContainsString('#654321', $this->media->readFile((string) $after));
        self::assertTrue($this->media->isExist($before), 'pages still cached by Varnish link the previous file');
    }

    public function testFilesOlderThanTheCacheWindowArePruned(): void
    {
        $old = 'openlabel/1/openlabel.000000000000.css';
        $this->media->writeFile($old, '.x{}');
        touch($this->media->getAbsolutePath($old), time() - 3 * 86400);

        $this->regenerator->regenerateAll();

        self::assertFalse($this->media->isExist($old));
    }

    public function testEnsureGeneratesOnFirstUseAfterAFreshInstall(): void
    {
        self::assertNull($this->storage->current(1));

        $path = $this->regenerator->ensure(1);

        self::assertTrue($this->media->isExist($path));
    }

    public function testCurrentFileSurvivesACacheFlush(): void
    {
        $path = $this->regenerator->regenerateAll()[1];
        Bootstrap::getObjectManager()->get(CacheInterface::class)->remove(Storage::CACHE_KEY_PREFIX . '1');

        self::assertSame($path, $this->storage->current(1));
    }

    #[AppArea('adminhtml')]
    #[DataFixture(DesignFixture::class, ['bg_color' => '#abcdef'], 'design')]
    public function testDesignDeleteRemovesItsRule(): void
    {
        $id = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $this->regenerator->regenerateAll();

        Bootstrap::getObjectManager()->get(DesignRepositoryInterface::class)->deleteById($id);

        self::assertStringNotContainsString('.ol-d-' . $id . '{', $this->media->readFile((string) $this->storage->current(1)));
    }
}
