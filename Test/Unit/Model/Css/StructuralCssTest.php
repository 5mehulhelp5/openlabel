<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Css;

use PHPUnit\Framework\TestCase;

/**
 * The structural stylesheet's motion rules (1.1): CSS only, and off for shoppers who ask for reduced motion.
 */
class StructuralCssTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        $this->css = (string) file_get_contents(dirname(__DIR__, 4) . '/view/base/web/css/openlabel.css');
    }

    public function testEveryAnimationAndHoverEffectHasARule(): void
    {
        foreach (['.ol-anim-fade', '.ol-anim-pop', '.ol-hover-grow', '.ol-hover-lift', '@keyframes ol-fade', '@keyframes ol-pop'] as $rule) {
            self::assertStringContainsString($rule, $this->css);
        }
    }

    public function testHoverEffectsOnlyApplyToPointersThatHover(): void
    {
        self::assertMatchesRegularExpression('/@media \\(hover: ?hover\\)[^@]*\\.ol-hover-grow/s', $this->css);
    }

    public function testReducedMotionSwitchesAnimationsAndTransitionsOff(): void
    {
        self::assertSame(1, preg_match('/@media \\(prefers-reduced-motion: ?reduce\\) ?\\{(.*?\\})\\s*\\}/s', $this->css, $match));
        self::assertStringContainsString('animation: none', $match[1]);
        self::assertStringContainsString('transition: none', $match[1]);
    }
}
