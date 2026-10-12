<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Config\Backend;

use Iranimij\OpenLabel\Model\Config\Backend\GlobalCss;
use Iranimij\OpenLabel\Model\Css\Regenerator;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GlobalCssTest extends TestCase
{
    private int $regenerated = 0;

    private ?string $regeneratedWith = null;

    public function testTheCssIsSanitizedBeforeItIsStored(): void
    {
        $value = $this->value('.ol-label{color:red}', '.ol-label{letter-spacing:1px}<script>x</script>@import url(a.css);');

        $value->beforeSave();

        self::assertSame('.ol-label{letter-spacing:1px}x', $value->getValue());
    }

    public function testOnlyDangerousCssIsStoredAsEmpty(): void
    {
        $value = $this->value('', '@import url(https://evil.test/a.css);');

        $value->beforeSave();

        self::assertSame('', $value->getValue());
    }

    public function testTheStylesheetIsRebuiltWhenTheCssChanges(): void
    {
        $changed = $this->value('.a{color:red}', '.a{color:blue}');
        $changed->afterSave();
        self::assertSame(1, $this->regenerated);
        self::assertSame('.a{color:blue}', $this->regeneratedWith, 'the saved value, not the configuration cached for this request');

        $same = $this->value('.a{color:red}', '.a{color:red}');
        $same->afterSave();
        self::assertSame(1, $this->regenerated, 'saving the section unchanged does not rebuild');
    }

    private function value(string $old, string $new): GlobalCss
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($old);
        $regenerator = $this->createStub(Regenerator::class);
        $regenerator->method('regenerateAll')->willReturnCallback(function (?string $css = null): array {
            $this->regenerated++;
            $this->regeneratedWith = $css;

            return [];
        });
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $context->method('getLogger')->willReturn($this->createStub(LoggerInterface::class));
        $context->method('getCacheManager')->willReturn($this->createStub(CacheInterface::class));
        $value = new GlobalCss(
            $context,
            $this->createStub(Registry::class),
            $config,
            $this->createStub(TypeListInterface::class),
            new Sanitizer(),
            $regenerator
        );
        $value->setData(['path' => 'openlabel_css/general/global_css', 'scope' => 'default', 'scope_id' => 0, 'value' => $new]);

        return $value;
    }
}
