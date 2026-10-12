<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Model\ImportExport;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\DesignInterfaceFactory;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Console\Export as ExportCommand;
use Iranimij\OpenLabel\Console\Import as ImportCommand;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\ImportExport\Exporter;
use Iranimij\OpenLabel\Model\ImportExport\Importer;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * openlabel:export / openlabel:import: a round trip restores labels, designs, store texts, conditions, images and the
 * same index rows; dry runs and broken files write nothing. Everything this test creates is named `ol-ie …`.
 */
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
#[DataFixture(StoreFixture::class, ['code' => 'ol_ie'], 'store_ie')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-ie-a', 'price' => 10], 'a')]
#[DataFixture(ProductFixture::class, ['sku' => 'ol-ie-b', 'price' => 20], 'b')]
class ImportExportTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40"><rect width="80" height="40" fill="#e11d48"/></svg>';

    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $labelIds = $connection->fetchCol(
            $connection->select()->from($resource->getTableName('openlabel_label'), ['label_id'])->where('name LIKE ?', 'ol-ie %')
        );
        if ($labelIds !== []) {
            $connection->delete($resource->getTableName('openlabel_index'), ['label_id IN (?)' => $labelIds]);
            $connection->delete($resource->getTableName('openlabel_label'), ['label_id IN (?)' => $labelIds]);
        }
        $connection->delete($resource->getTableName('openlabel_design'), ['name LIKE ?' => 'ol-ie %', 'is_system = ?' => 0]);
        $media = Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA);
        foreach (['ol-ie.svg', 'ol-ie-1.svg', 'ol-ie-evil.svg'] as $file) {
            $media->delete(ImageUrl::MEDIA_DIR . '/designs/' . $file);
        }
    }

    public function testRoundTripRestoresEverythingAndTheSameIndexRows(): void
    {
        $storeId = (int) $this->fixture('store_ie')->getId();
        $text = $this->textDesign($storeId);
        $image = $this->imageDesign();
        $system = $this->systemDesignName();
        $sale = $this->label('ol-ie Sale', 'ol-ie-a', (int) $text->getDesignId(), [
            'priority' => 2,
            'store_ids' => [$storeId],
            'customer_group_ids' => [1, 2],
            'valid_from' => '2026-01-01 00:00:00',
            'valid_to' => '2036-01-01 00:00:00',
            'apply_to_parent' => true,
            'hide_on_zero_variable' => true,
            'click_url' => '/sale.html',
            'click_nofollow' => true,
            'placements' => [
                ['area' => 'listing', 'position' => 'tr', 'offset_x' => 4, 'gap' => 6, 'max_labels' => 2, 'stacking' => 'horizontal'],
                ['area' => 'product', 'position' => 'bl', 'design_id' => (int) $image->getDesignId(), 'pin_physical_side' => true],
            ],
        ]);
        $new = $this->label('ol-ie New', 'ol-ie-a, ol-ie-b', $this->systemDesignId($system));
        $before = [
            'ol-ie Sale' => $this->indexRows((int) $sale->getLabelId()),
            'ol-ie New' => $this->indexRows((int) $new->getLabelId()),
        ];
        $exported = $this->exporter()->export([(int) $sale->getLabelId(), (int) $new->getLabelId()]);
        self::assertSame(2, $exported['version']);
        self::assertCount(3, $exported['designs'], 'text, image and the built-in design');
        self::assertSame(
            ['animation' => 'fade', 'hover_effect' => 'lift', 'sizes' => ['sm' => ['width' => 24, 'font_size' => 11]]],
            array_intersect_key($exported['designs'][0], array_flip(['animation', 'hover_effect', 'sizes']))
        );
        self::assertSame(
            ['hide_on_zero_variable' => true, 'click_url' => '/sale.html', 'click_nofollow' => true],
            array_intersect_key($exported['labels'][0], array_flip(['hide_on_zero_variable', 'click_url', 'click_nofollow']))
        );
        self::assertContains(['key' => 'system:' . $system, 'name' => $system, 'is_system' => true], $exported['designs']);

        $this->tearDown();
        $result = $this->importer()->import($this->roundTrip($exported));

        self::assertSame([], $result->errors);
        self::assertSame([2, 0, 2, 0, 1], [
            $result->labelsCreated, $result->labelsUpdated, $result->designsCreated, $result->designsUpdated, $result->designsReused,
        ]);
        self::assertSame($this->normalise($exported), $this->normalise($this->exporter()->export($result->labelIds)));
        $after = [];
        foreach ($result->labelIds as $labelId) {
            $name = (string) $this->connection()->fetchOne('SELECT name FROM openlabel_label WHERE label_id = ?', [$labelId]);
            $after[$name] = $this->indexRows($labelId);
        }
        self::assertSame($before, $after);
        self::assertNotEmpty($after['ol-ie Sale']);
    }

    public function testVersionOneFileImportsWithOneDotOneDefaults(): void
    {
        $label = $this->label('ol-ie Old', 'ol-ie-a', (int) $this->textDesign()->getDesignId());
        $file = $this->roundTrip($this->exporter()->export([(int) $label->getLabelId()]));
        $this->tearDown();
        $file['version'] = 1;
        foreach (['animation', 'hover_effect', 'sizes'] as $key) {
            unset($file['designs'][0][$key]);
        }
        foreach (['hide_on_zero_variable', 'click_url', 'click_nofollow'] as $key) {
            unset($file['labels'][0][$key]);
        }

        $result = $this->importer()->import($file);

        self::assertSame([], $result->errors);
        $om = Bootstrap::getObjectManager();
        $imported = $om->get(\Iranimij\OpenLabel\Api\LabelRepositoryInterface::class)->getById($result->labelIds[0]);
        self::assertFalse($imported->isHideOnZeroVariable());
        self::assertNull($imported->getClickUrl());
        self::assertFalse($imported->isClickNofollow());
        $design = $om->get(DesignRepositoryInterface::class)->getById((int) $imported->getDesignId());
        self::assertSame(['none', 'none', []], [$design->getAnimation(), $design->getHoverEffect(), $design->getSizes()]);
    }

    public function testDryRunWritesNothing(): void
    {
        $label = $this->label('ol-ie Dry', 'ol-ie-a', (int) $this->textDesign()->getDesignId());
        $exported = $this->exporter()->export([(int) $label->getLabelId()]);
        $this->tearDown();
        $counts = $this->counts();

        $result = $this->importer()->import($this->roundTrip($exported), true);

        self::assertSame([], $result->errors);
        self::assertSame(1, $result->labelsCreated);
        self::assertSame([], $result->labelIds);
        self::assertSame($counts, $this->counts());
    }

    public function testABrokenFileWritesNothingAndSaysWhatToFix(): void
    {
        $label = $this->label('ol-ie Broken', 'ol-ie-a', (int) $this->textDesign()->getDesignId());
        $file = $this->exporter()->export([(int) $label->getLabelId()]);
        $this->tearDown();
        $file['labels'][0]['stores'] = ['no_such_store'];
        $file['labels'][0]['customer_groups'] = ['No such group'];
        $file['labels'][0]['conditions']['conditions'][] = ['type' => \Magento\Framework\DataObject::class];
        $file['labels'][0]['placements'][0]['position'] = 'side';
        $counts = $this->counts();

        $result = $this->importer()->import($this->roundTrip($file));

        self::assertSame([
            'labels[0] "ol-ie Broken": this shop has no store view with code "no_such_store".',
            'labels[0] "ol-ie Broken": this shop has no customer group with code "No such group".',
            'labels[0] "ol-ie Broken": condition type "Magento\Framework\DataObject" is not an OpenLabel condition.',
            'labels[0] "ol-ie Broken": Placement position "side" is not available in this version.',
        ], $result->errors);
        self::assertSame($counts, $this->counts());
    }

    public function testReplaceUpdatesLabelsAndDesignsWithTheSameName(): void
    {
        $label = $this->label('ol-ie Replace', 'ol-ie-a', (int) $this->textDesign()->getDesignId(), ['priority' => 1]);
        $file = $this->exporter()->export([(int) $label->getLabelId()]);
        $file['labels'][0]['priority'] = 7;
        $file['designs'][0]['bg_color'] = '#166534';

        $result = $this->importer()->import($this->roundTrip($file), false, true);

        self::assertSame([], $result->errors);
        self::assertSame([0, 1, 0, 1], [$result->labelsCreated, $result->labelsUpdated, $result->designsCreated, $result->designsUpdated]);
        self::assertSame([(int) $label->getLabelId()], $result->labelIds);
        self::assertSame('7', (string) $this->connection()->fetchOne('SELECT priority FROM openlabel_label WHERE label_id = ?', [$label->getLabelId()]));
        self::assertSame('1', (string) $this->connection()->fetchOne("SELECT COUNT(*) FROM openlabel_design WHERE name = 'ol-ie Text'"));
        self::assertSame('#166534', (string) $this->connection()->fetchOne("SELECT bg_color FROM openlabel_design WHERE name = 'ol-ie Text'"));
    }

    public function testImportedSvgIsSanitised(): void
    {
        $label = $this->label('ol-ie Svg', 'ol-ie-a', (int) $this->imageDesign()->getDesignId());
        $file = $this->exporter()->export([(int) $label->getLabelId()]);
        $this->tearDown();
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $file['designs'][0]['image'] = ['file' => 'ol-ie-evil.svg', 'width' => 80, 'height' => 40, 'content' => base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40" onload="alert(1)"><script>alert(2)</script><rect width="80" height="40"/></svg>'
        )];

        $result = $this->importer()->import($this->roundTrip($file));

        self::assertSame([], $result->errors);
        $media = Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryRead(DirectoryList::MEDIA);
        $svg = $media->readFile(ImageUrl::MEDIA_DIR . '/designs/ol-ie-evil.svg');
        self::assertStringNotContainsString('script', $svg);
        self::assertStringNotContainsString('onload', $svg);
        self::assertStringContainsString('<rect', $svg);
    }

    public function testCommandsExportToAFileAndImportIt(): void
    {
        $label = $this->label('ol-ie Cli', 'ol-ie-a', (int) $this->textDesign()->getDesignId());
        $tmp = Bootstrap::getObjectManager()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::TMP);
        $path = $tmp->getAbsolutePath('ol-ie-export.json');

        $export = new CommandTester(Bootstrap::getObjectManager()->create(ExportCommand::class));
        self::assertSame(0, $export->execute(['file' => $path, '--labels' => (string) $label->getLabelId()]));
        self::assertStringContainsString('Exported 1 label(s) and 1 design(s)', $export->getDisplay());

        $import = new CommandTester(Bootstrap::getObjectManager()->create(ImportCommand::class));
        self::assertSame(0, $import->execute(['file' => $path, '--dry-run' => true]));
        self::assertStringContainsString('Dry run, nothing written. Would import: 1 label(s) new', $import->getDisplay());

        $tmp->writeFile('ol-ie-broken.json', '{"format": "something else"}');
        self::assertSame(1, $import->execute(['file' => $tmp->getAbsolutePath('ol-ie-broken.json')]));
        self::assertStringContainsString('This is not an OpenLabel export file.', $import->getDisplay());
        $tmp->delete('ol-ie-export.json');
        $tmp->delete('ol-ie-broken.json');
    }

    /**
     * What the file looks like after being written and read back as JSON (lists, strings, no objects).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function roundTrip(array $data): array
    {
        return json_decode((string) json_encode($data), true);
    }

    /**
     * Design keys carry this shop's design ids; compare files by the designs' order instead.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        $map = [];
        foreach ($data['designs'] as $i => $design) {
            if (str_starts_with($design['key'], 'design:')) {
                $map[$design['key']] = 'design#' . $i;
            }
        }
        $encoded = (string) json_encode($data);

        return json_decode(strtr($encoded, array_combine(
            array_map(static fn (string $k): string => '"' . $k . '"', array_keys($map)),
            array_map(static fn (string $v): string => '"' . $v . '"', $map)
        ) ?: []), true);
    }

    private function textDesign(?int $storeId = null): DesignInterface
    {
        $om = Bootstrap::getObjectManager();
        $design = $om->get(DesignInterfaceFactory::class)->create();
        $design->setName('ol-ie Text')->setType('text')->setShape('pill')->setBgColor('#e11d48')->setTextColor('#ffffff')
            ->setSizeMode('percent')->setWidth(18)->setFontSize(14)->setOpacity(100)->setCustomCss('.ol-d-1{letter-spacing:1px}')
            ->setAnimation('fade')->setHoverEffect('lift')->setSizes(['sm' => ['width' => 24, 'font_size' => 11]]);
        $design->setText('Sale -{SAVE_PERCENT}%');
        if ($storeId !== null) {
            $design->setText('Angebot', $storeId);
        }

        return $om->get(DesignRepositoryInterface::class)->save($design);
    }

    private function imageDesign(): DesignInterface
    {
        $om = Bootstrap::getObjectManager();
        $om->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA)
            ->writeFile(ImageUrl::MEDIA_DIR . '/designs/ol-ie.svg', self::SVG);
        $design = $om->get(DesignInterfaceFactory::class)->create();
        $design->setName('ol-ie Image')->setType('image')->setImagePath('designs/ol-ie.svg')->setImageWidth(80)
            ->setImageHeight(40)->setSizeMode('px')->setWidth(80)->setFontSize(14)->setOpacity(100);
        $design->setAltText('New in');

        return $om->get(DesignRepositoryInterface::class)->save($design);
    }

    private function systemDesignName(): string
    {
        return (string) $this->connection()->fetchOne('SELECT name FROM openlabel_design WHERE is_system = 1 ORDER BY design_id LIMIT 1');
    }

    private function systemDesignId(string $name): int
    {
        return (int) $this->connection()->fetchOne('SELECT design_id FROM openlabel_design WHERE is_system = 1 AND name = ?', [$name]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function label(string $name, string $skus, int $designId, array $data = []): \Iranimij\OpenLabel\Api\Data\LabelInterface
    {
        $tree = Bootstrap::getObjectManager()->get(Json::class)->serialize([
            'type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'conditions' => [
                ['type' => ProductCondition::class, 'attribute' => 'sku', 'operator' => '()', 'value' => $skus],
            ],
        ]);
        /** @var \Iranimij\OpenLabel\Api\Data\LabelInterface $label */
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply(array_merge([
            'name' => $name,
            'design_id' => $designId,
            'conditions_serialized' => $tree,
        ], $data));

        return $label;
    }

    /**
     * @return array<int, string> index rows without the label id: "product/store/group/parent"
     */
    private function indexRows(int $labelId): array
    {
        $rows = $this->connection()->fetchCol(
            "SELECT CONCAT_WS('/', product_id, store_id, customer_group_id, IFNULL(parent_product_id, '-')) FROM openlabel_index"
            . ' WHERE label_id = ? ORDER BY 1',
            [$labelId]
        );

        return $rows;
    }

    /**
     * @return array{int, int}
     */
    private function counts(): array
    {
        return [
            (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM openlabel_label'),
            (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM openlabel_design'),
        ];
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function exporter(): Exporter
    {
        return Bootstrap::getObjectManager()->get(Exporter::class);
    }

    private function importer(): Importer
    {
        return Bootstrap::getObjectManager()->get(Importer::class);
    }

    private function fixture(string $name): \Magento\Framework\DataObject
    {
        return DataFixtureStorageManager::getStorage()->get($name);
    }
}
