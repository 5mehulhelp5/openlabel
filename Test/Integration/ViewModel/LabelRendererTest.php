<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\ViewModel;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Block\Css;
use Iranimij\OpenLabel\Block\Identities;
use Iranimij\OpenLabel\Model\Rule\Condition\Combine;
use Iranimij\OpenLabel\Model\Rule\Condition\Product as ProductCondition;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Iranimij\OpenLabel\Test\Integration\Helper\QueryCounter;
use Iranimij\OpenLabel\ViewModel\LabelRenderer;
use Iranimij\OpenLabel\ViewModel\Labels;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Iranimij\OpenLabel\Test\Fixture\ScheduledSearchIndex;
use PHPUnit\Framework\TestCase;

/**
 * Storefront markup of labels (02 · Architecture §4, 10 · Front-end Review FE6/FE7).
 */
#[AppArea('frontend')]
#[DbIsolation(false)]
#[DataFixture(ScheduledSearchIndex::class)]
class LabelRendererTest extends TestCase
{
    private const SKU_PREFIX = 'ol-render-';
    private const LISTING_SIZE = 36;

    /** @var int[] */
    private ?array $labelIds = [];

    private ?Labels $labels = null;

    private ?LabelRenderer $renderer = null;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->labels = $om->create(Labels::class);
        $this->renderer = $om->create(LabelRenderer::class, ['labels' => $this->labels]);
    }

    protected function tearDown(): void
    {
        $repository = Bootstrap::getObjectManager()->get(LabelRepositoryInterface::class);
        foreach ($this->labelIds ?? [] as $id) {
            try {
                $repository->deleteById($id);
            } catch (\Exception) {
                // already gone
            }
        }
    }

    // 2.4.7: method-level fixtures replace the class-level ones, so the search index is scheduled here too.
    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(DesignFixture::class, ['store_texts' => [0 => ['text' => 'Sale <b>now</b><script>x</script>']]], 'design')]
    public function testTextLabelMarkupFollowsTheCssContract(): void
    {
        $product = $this->products(1)[0];
        $this->label(['placements' => [['area' => 'listing', 'position' => 'tr', 'stacking' => 'horizontal', 'offset_x' => 6]]]);

        $html = $this->renderer()->getStacksHtml($product, 'listing');

        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        self::assertMatchesRegularExpression('#<span class="ol-stack ol-stack--h ol-pos-te ol-p-\d+">#', $html);
        self::assertStringContainsString('<span class="ol-label ol-label--text ol-d-' . $designId . ' ol-shape-pill">Sale <b>now</b>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('style=', $html, 'no inline styles (CSP)');
        self::assertStringNotContainsString('data-ol-label', $html);
    }

    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(DesignFixture::class, ['store_texts' => [0 => ['text' => 'Sale', 'tooltip' => 'Ends <b>Sunday</b>']]], 'design')]
    public function testLinkAndTooltipMarkupWithoutALinkInsideTheCardLink(): void
    {
        $product = $this->products(1)[0];
        $this->label([
            'click_url' => '/sale.html',
            'click_nofollow' => true,
            'placements' => [['area' => 'listing', 'position' => 'tl'], ['area' => 'product', 'position' => 'tl']],
        ]);

        $card = $this->renderer()->getStacksHtml($product, 'listing');
        $page = $this->renderer()->getStacksHtml($product, 'product');

        self::assertStringNotContainsString('<a ', $card, 'the card image is already a link');
        self::assertStringNotContainsString('tabindex', $card, 'nothing focusable inside the card link');
        self::assertMatchesRegularExpression('#<span class="[^"]*ol-has-tip" aria-describedby="(ol-tip-\d+-\d+)">Sale</span><span class="ol-tip" role="tooltip" id="\1">Ends <b>Sunday</b></span>#', $card);
        self::assertMatchesRegularExpression('#<a class="[^"]*ol-label--link ol-has-tip" href="https?://[^"]+/sale\.html" rel="nofollow" aria-describedby="ol-tip-\d+-\d+">Sale</a><span class="ol-tip"#', $page);
    }

    #[DataFixture(DesignFixture::class, [
        'type' => DesignInterface::TYPE_IMAGE, 'image_path' => 'designs/new.svg', 'image_width' => 80, 'image_height' => 40,
        'store_texts' => [0 => ['text' => null, 'alt_text' => 'New in']],
    ], 'design')]
    public function testImageLabelHasDimensionsAltAndLoading(): void
    {
        $product = $this->products(1)[0];
        $this->label();

        $html = $this->renderer()->getStacksHtml($product, 'listing', 'eager');

        self::assertMatchesRegularExpression(
            '#<img src="[^"]+/media/openlabel/designs/new\.svg" alt="New in" width="80" height="40" loading="eager" decoding="async">#',
            $html
        );
    }

    // 2.4.7: method-level fixtures replace the class-level ones, so the search index is scheduled here too.
    #[DataFixture(ScheduledSearchIndex::class)]
    #[Config('openlabel/general/debug', 1, 'store', 'default')]
    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testDebugModeAddsTheLabelId(): void
    {
        $product = $this->products(1)[0];
        $id = $this->label();

        self::assertStringContainsString('data-ol-label="' . $id . '"', $this->renderer()->getStacksHtml($product, 'listing'));
    }

    // 2.4.7: method-level fixtures replace the class-level ones, so the search index is scheduled here too.
    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(DesignFixture::class, ['store_texts' => [0 => ['text' => '-{SAVE_PERCENT}%']]], 'design')]
    public function testAListingOf36ProductsRendersWithOneQuery(): void
    {
        $products = $this->products(self::LISTING_SIZE);
        $this->label();
        $om = Bootstrap::getObjectManager();
        $this->labels->remember($products);
        $renderer = $this->renderer();

        $html = '';
        $queries = $om->get(QueryCounter::class)->count(function () use ($products, $renderer, &$html): void {
            foreach ($products as $product) {
                $html .= $renderer->getStacksHtml($product, 'listing');
            }
        });

        self::assertSame(1, $queries, '02 · Architecture §10: ≤ 1 extra query per listing');
        self::assertSame(self::LISTING_SIZE, substr_count($html, 'class="ol-stack '));
    }

    // 2.4.7: method-level fixtures replace the class-level ones, so the search index is scheduled here too.
    #[DataFixture(ScheduledSearchIndex::class)]
    #[DataFixture(DesignFixture::class, [], 'design')]
    public function testIdentitiesBlockTagsThePageWithRenderedLabelsAndDesigns(): void
    {
        $product = $this->products(1)[0];
        $id = $this->label();
        $this->renderer()->getStacksHtml($product, 'listing');

        $block = Bootstrap::getObjectManager()->get(LayoutInterface::class)
            ->createBlock(Identities::class, '', ['labelRenderer' => $this->renderer]);

        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        self::assertContains('openlabel_' . $id, $block->getIdentities());
        self::assertContains('openlabel_design_' . $designId, $block->getIdentities());
        self::assertSame('', $block->toHtml());
    }

    public function testCssBlockLinksTheGeneratedStylesheet(): void
    {
        $block = Bootstrap::getObjectManager()->get(LayoutInterface::class)->createBlock(Css::class);

        self::assertMatchesRegularExpression(
            '#^<link rel="stylesheet" href="[^"]+/media/openlabel/1/openlabel\.[0-9a-f]{12}\.css" data-openlabel="css">$#',
            trim($block->toHtml())
        );
    }

    #[Config('openlabel/general/enabled', 0, 'store', 'default')]
    public function testCssBlockIsEmptyWhenTheModuleIsDisabled(): void
    {
        self::assertSame('', Bootstrap::getObjectManager()->get(LayoutInterface::class)->createBlock(Css::class)->toHtml());
    }

    private function renderer(): LabelRenderer
    {
        return $this->renderer;
    }

    /**
     * @param array<string, mixed> $data
     * @return int
     */
    private function label(array $data = []): int
    {
        $label = Bootstrap::getObjectManager()->get(LabelFixture::class)->apply(array_merge([
            'name' => 'Render test',
            'design_id' => (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId(),
            'conditions_serialized' => json_encode([
                'type' => Combine::class,
                'aggregator' => 'all', 'value' => '1',
                'conditions' => [[
                    'type' => ProductCondition::class,
                    'attribute' => 'sku', 'operator' => '{}', 'value' => self::SKU_PREFIX,
                ]],
            ]),
        ], $data));
        $this->labelIds[] = (int) $label->getLabelId();

        return (int) $label->getLabelId();
    }

    /**
     * @param int $count
     * @return \Magento\Catalog\Model\Product[]
     */
    private function products(int $count): array
    {
        $om = Bootstrap::getObjectManager();
        $skus = array_map(static fn (int $i): string => sprintf('%s%02d', self::SKU_PREFIX, $i), range(1, self::LISTING_SIZE));
        $collection = $om->create(CollectionFactory::class)->create()->addAttributeToFilter('sku', ['in' => $skus]);
        if ($collection->getSize() < self::LISTING_SIZE) {
            $existing = $collection->getColumnValues('sku');
            foreach (array_diff($skus, $existing) as $sku) {
                $om->get(ProductFixture::class)->apply(['sku' => $sku, 'price' => 20, 'special_price' => 15]);
            }
        }
        $collection = $om->create(CollectionFactory::class)->create()
            ->addAttributeToFilter('sku', ['in' => $skus])
            ->addAttributeToSelect(['name', 'special_price', 'price'])
            ->addPriceData(0, 1)
            ->setOrder('sku', 'ASC')
            ->setPageSize($count);

        return array_values($collection->getItems());
    }
}
