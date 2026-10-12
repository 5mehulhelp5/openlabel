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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class GlobalCssTest extends TestCase
{
    private int $regenerated = 0;

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

        $same = $this->value('.a{color:red}', '.a{color:red}');
        $same->afterSave();
        self::assertSame(1, $this->regenerated, 'saving the section unchanged does not rebuild');
    }

    private function value(string $old, string $new): GlobalCss
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($old);
        $regenerator = $this->createStub(Regenerator::class);
        $regenerator->method('regenerateAll')->willReturnCallback(function (): array {
            $this->regenerated++;

            return [];
        });
        /** @var GlobalCss $value */
        $value = (new ObjectManager($this))->getObject(GlobalCss::class, [
            'config' => $config,
            'sanitizer' => new Sanitizer(),
            'regenerator' => $regenerator,
        ]);
        $value->setData(['path' => 'openlabel_css/general/global_css', 'scope' => 'default', 'scope_id' => 0, 'value' => $new]);

        return $value;
    }
}
