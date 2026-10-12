<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Integration\Webapi;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\Data\LabelInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Plugin\Webapi\DesignGuardPlugin;
use Iranimij\OpenLabel\Test\Fixture\Design as DesignFixture;
use Iranimij\OpenLabel\Test\Fixture\Label as LabelFixture;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\Config\Reader\Dom as DiReader;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Model\Config as WebapiConfig;
use PHPUnit\Framework\TestCase;

/**
 * REST API for labels and designs (1.1): routes and permissions, what a client reads and sends back, and the rules
 * the admin forms enforce (built-in designs locked, custom CSS behind its own permission, uploaded images only).
 * Requests are converted by Magento's own web API input and output processors, as the REST controller does.
 */
#[AppArea('webapi_rest')]
#[DbIsolation(true)]
class RestApiTest extends TestCase
{
    public function testEveryRouteNeedsTheMatchingPermission(): void
    {
        $routes = Bootstrap::getObjectManager()->get(WebapiConfig::class)->getServices()['routes'];
        $expected = [
            '/V1/openlabel/labels' => ['GET' => 'getList', 'POST' => 'save'],
            '/V1/openlabel/labels/:labelId' => ['GET' => 'getById', 'PUT' => 'save', 'DELETE' => 'deleteById'],
            '/V1/openlabel/designs' => ['GET' => 'getList', 'POST' => 'save'],
            '/V1/openlabel/designs/:designId' => ['GET' => 'getById', 'PUT' => 'save', 'DELETE' => 'deleteById'],
        ];

        foreach ($expected as $url => $methods) {
            $resource = str_contains($url, 'labels') ? 'Iranimij_OpenLabel::labels' : 'Iranimij_OpenLabel::designs';
            $service = str_contains($url, 'labels') ? LabelRepositoryInterface::class : DesignRepositoryInterface::class;
            self::assertSame(array_keys($methods), array_keys($routes[$url]), $url);
            foreach ($methods as $httpMethod => $serviceMethod) {
                $route = $routes[$url][$httpMethod];
                self::assertSame($service, $route['service']['class'], "$httpMethod $url");
                self::assertSame($serviceMethod, $route['service']['method'], "$httpMethod $url");
                self::assertSame([$resource], array_keys($route['resources']), "$httpMethod $url");
            }
        }
    }

    #[DataFixture(DesignFixture::class, as: 'design')]
    #[DataFixture(LabelFixture::class, ['design_id' => '$design.design_id$', 'name' => 'API label'], 'label')]
    public function testALabelReadThroughTheApiCanBeSentBack(): void
    {
        $repository = Bootstrap::getObjectManager()->get(LabelRepositoryInterface::class);
        $labelId = (int) DataFixtureStorageManager::getStorage()->get('label')->getLabelId();
        $before = $repository->getById($labelId);

        $read = $this->read(LabelRepositoryInterface::class, 'getById', $before);
        $read['name'] = 'API label, renamed';
        /** @var LabelInterface $sent */
        $sent = $this->send(LabelRepositoryInterface::class, 'save', ['label' => $read])[0];
        $repository->save($sent);

        $after = $repository->getById($labelId);
        self::assertSame('API label, renamed', $after->getName());
        self::assertSame($before->getCreatedAt(), $after->getCreatedAt(), 'timestamps are read-only');
        self::assertCount(count($before->getPlacements()), $after->getPlacements());
        self::assertSame($before->getPlacements()[0]->getPosition(), $after->getPlacements()[0]->getPosition());
    }

    #[DataFixture(DesignFixture::class, [
        'store_texts' => [0 => ['text' => 'Sale', 'alt_text' => null, 'tooltip' => 'Ends Sunday'], 1 => ['text' => 'Sale EN', 'alt_text' => null, 'tooltip' => null]],
        'sizes' => ['sm' => ['width' => 40]],
    ], 'design')]
    public function testADesignKeepsItsStoreTextsAndSizesThroughTheApi(): void
    {
        $repository = Bootstrap::getObjectManager()->get(DesignRepositoryInterface::class);
        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();

        $read = $this->read(DesignRepositoryInterface::class, 'getById', $repository->getById($designId));
        self::assertSame('Sale EN', $read['store_texts'][1]['text'], 'keyed by store ID');
        self::assertSame('Ends Sunday', $read['store_texts'][0]['tooltip']);
        self::assertSame(['sm' => ['width' => 40]], $read['sizes']);
        $criteria = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class)->addFilter('design_id', $designId)->create();
        $listed = $this->read(DesignRepositoryInterface::class, 'getList', $repository->getList($criteria));
        self::assertSame($read['store_texts'], $listed['items'][0]['store_texts'], 'the same in a list');
        self::assertSame($read['sizes'], $listed['items'][0]['sizes']);

        $read['store_texts'][1]['text'] = 'Deal';
        $repository->save($this->send(DesignRepositoryInterface::class, 'save', ['design' => $read])[0]);

        $after = $repository->getById($designId);
        self::assertSame('Deal', $after->getText(1));
        self::assertSame('Ends Sunday', $after->getTooltip());
        self::assertSame(['sm' => ['width' => 40]], $after->getSizes());
    }

    #[DataFixture(DesignFixture::class, ['name' => 'Locked'], 'design')]
    public function testBuiltInDesignsCannotBeChangedOrDeletedOverTheApi(): void
    {
        $om = Bootstrap::getObjectManager();
        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        $resource = $om->get(ResourceConnection::class);
        $resource->getConnection()->update($resource->getTableName('openlabel_design'), ['is_system' => 1], ['design_id = ?' => $designId]);
        $repository = $om->get(DesignRepositoryInterface::class);
        $read = $this->read(DesignRepositoryInterface::class, 'getById', $repository->getById($designId));
        $read['name'] = 'Changed';

        try {
            $repository->save($this->send(DesignRepositoryInterface::class, 'save', ['design' => $read])[0]);
            self::fail('a built-in design was changed');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Built-in designs are locked', $e->getMessage());
        }
        try {
            $repository->deleteById($designId);
            self::fail('a built-in design was deleted');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Built-in designs are locked', $e->getMessage());
        }
        self::assertSame('Locked', $repository->getById($designId)->getName());
    }

    public function testANewDesignCannotClaimToBeBuiltIn(): void
    {
        $repository = Bootstrap::getObjectManager()->get(DesignRepositoryInterface::class);

        $saved = $repository->save($this->send(DesignRepositoryInterface::class, 'save', ['design' => [
            'name' => 'Claims system', 'type' => 'text', 'width' => 20, 'system' => true,
            'store_texts' => [0 => ['text' => 'New']],
        ]])[0]);

        self::assertFalse($repository->getById((int) $saved->getDesignId())->isSystem());
    }

    public function testAnImageDesignMustUseAnUploadedLabelImage(): void
    {
        $repository = Bootstrap::getObjectManager()->get(DesignRepositoryInterface::class);
        foreach (['../../app/etc/env.php', 'designs/../../../app/etc/env.php', '/etc/passwd', 'designs/not-uploaded.png'] as $path) {
            try {
                $repository->save($this->send(DesignRepositoryInterface::class, 'save', ['design' => [
                    'name' => 'Image', 'type' => 'image', 'width' => 20, 'image_path' => $path,
                    'store_texts' => [0 => ['alt_text' => 'Badge']],
                ]])[0]);
                self::fail($path . ' was accepted');
            } catch (LocalizedException $e) {
                self::assertStringContainsString('Upload the image in the admin', $e->getMessage(), $path);
            }
        }
    }

    #[DataFixture(DesignFixture::class, ['name' => 'Styled'], 'design')]
    public function testCustomCssNeedsItsOwnPermission(): void
    {
        $om = Bootstrap::getObjectManager();
        $designId = (int) DataFixtureStorageManager::getStorage()->get('design')->getDesignId();
        // Set directly: the fixture saved through the guard, which dropped the CSS (the test has no permission).
        $resource = $om->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('openlabel_design'),
            ['custom_css' => '.ol-label{letter-spacing:1px}'],
            ['design_id = ?' => $designId]
        );
        $repository = $om->get(DesignRepositoryInterface::class);
        $read = $this->read(DesignRepositoryInterface::class, 'getById', $repository->getById($designId));
        $read['custom_css'] = '.ol-label{color:red}';

        $denied = $this->guard(false)->beforeSave(
            $repository,
            $this->send(DesignRepositoryInterface::class, 'save', ['design' => $read])[0]
        )[0];
        self::assertSame('.ol-label{letter-spacing:1px}', $denied->getCustomCss(), 'kept as it was');

        $new = $this->guard(false)->beforeSave(
            $repository,
            $this->send(DesignRepositoryInterface::class, 'save', ['design' => ['name' => 'N', 'type' => 'text', 'custom_css' => '.a{}']])[0]
        )[0];
        self::assertNull($new->getCustomCss());

        $read['custom_css'] = '@import url(https://evil.test/a.css);.ol-label{color:red}';
        $allowed = $this->guard(true)->beforeSave(
            $repository,
            $this->send(DesignRepositoryInterface::class, 'save', ['design' => $read])[0]
        )[0];
        self::assertSame('.ol-label{color:red}', $allowed->getCustomCss(), 'sanitized as in the admin');
    }

    public function testTheGuardRunsForTheWebApiOnly(): void
    {
        $reader = Bootstrap::getObjectManager()->get(DiReader::class);
        $found = [];
        foreach (['global', 'webapi_rest', 'webapi_soap', 'adminhtml', 'crontab'] as $area) {
            $config = $reader->read($area);
            $found[$area] = [
                isset($config[DesignRepositoryInterface::class]['plugins']['openlabel_webapi_guard']),
                isset($config[ServiceOutputProcessor::class]['plugins']['openlabel_design_output']),
            ];
        }

        $webapiOnly = ['global' => [false, false], 'webapi_rest' => [true, true], 'webapi_soap' => [true, true], 'adminhtml' => [false, false], 'crontab' => [false, false]];
        self::assertSame($webapiOnly, $found);
    }

    /**
     * @param string $service
     * @param string $method
     * @param mixed $result
     * @return array<string, mixed> what a REST client receives
     */
    private function read(string $service, string $method, mixed $result): array
    {
        return Bootstrap::getObjectManager()->get(ServiceOutputProcessor::class)->process($result, $service, $method);
    }

    /**
     * @param string $service
     * @param string $method
     * @param array<string, mixed> $body what a REST client sends
     * @return array<int, mixed> the service method's arguments
     */
    private function send(string $service, string $method, array $body): array
    {
        return Bootstrap::getObjectManager()->get(ServiceInputProcessor::class)->process($service, $method, $body);
    }

    /**
     * @param bool $customCssAllowed
     * @return DesignGuardPlugin
     */
    private function guard(bool $customCssAllowed): DesignGuardPlugin
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($customCssAllowed);

        return Bootstrap::getObjectManager()->create(DesignGuardPlugin::class, ['authorization' => $authorization]);
    }
}
