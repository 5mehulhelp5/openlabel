<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Label;

use Iranimij\OpenLabel\Api\Data\PlacementInterface;
use Iranimij\OpenLabel\Model\Label;
use Iranimij\OpenLabel\Model\Label\Validator;
use Iranimij\OpenLabel\Model\Placement;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private ObjectManager $objectManager;
    private Validator $validator;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->validator = new Validator();
    }

    public function testValidLabelHasNoErrors(): void
    {
        self::assertSame([], $this->validator->validate($this->label()));
    }

    public function testNameIsRequired(): void
    {
        $label = $this->label(['name' => '  ']);

        self::assertSame(['Enter a name for the label.'], $this->messages($label));
    }

    public function testAtLeastOnePlacementIsRequired(): void
    {
        $label = $this->label([], []);

        self::assertSame(['Pick at least one placement so the label appears somewhere.'], $this->messages($label));
    }

    public function testValidToMustBeAfterValidFrom(): void
    {
        $label = $this->label(['valid_from' => '2026-12-24 00:00:00', 'valid_to' => '2026-12-01 00:00:00']);

        self::assertSame(['The end date must be after the start date.'], $this->messages($label));
    }

    public function testDesignIsRequired(): void
    {
        $label = $this->label(['design_id' => null]);

        self::assertSame(['Choose a design for the label.'], $this->messages($label));
    }

    /**
     * @dataProvider clickUrls
     */
    #[DataProvider('clickUrls')]
    public function testClickUrlMustBeAWebLinkOrAPath(?string $url, bool $valid): void
    {
        $label = $this->label(['click_url' => $url]);

        self::assertSame(
            $valid ? [] : ['Enter a link that starts with https://, http:// or /.'],
            $this->messages($label)
        );
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function clickUrls(): array
    {
        return [
            'none' => [null, true],
            'empty' => ['', true],
            'https' => ['https://shop.test/sale', true],
            'http' => ['http://shop.test/sale', true],
            'path' => ['/sale.html', true],
            'javascript' => ['javascript:alert(1)', false],
            'data' => ['data:text/html,x', false],
            'protocol relative' => ['//evil.test/x', false],
            'relative without slash' => ['sale.html', false],
            'mixed case scheme' => ['JaVaScRiPt:alert(1)', false],
            'too long' => ['https://shop.test/' . str_repeat('a', 1007), false],
        ];
    }

    /**
     * @dataProvider invalidPlacements
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidPlacements')]
    public function testPlacementFieldsAreChecked(array $data, string $message): void
    {
        $label = $this->label([], [$this->placement($data)]);

        self::assertSame([$message], $this->messages($label));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPlacements(): array
    {
        return [
            'area' => [['area' => 'cart'], 'Placement area "cart" is not available in this version.'],
            'position' => [['position' => 'near_price'], 'Placement position "near_price" is not available in this version.'],
            'stacking' => [['stacking' => 'diagonal'], 'Stacking must be vertical or horizontal.'],
            'max too low' => [['max_labels' => 0], 'Max labels must be between 1 and 10.'],
            'max too high' => [['max_labels' => 11], 'Max labels must be between 1 and 10.'],
            'gap' => [['gap' => 65], 'Gap must be between 0 and 64 px.'],
            'offset x' => [['offset_x' => -201], 'Offsets must be between -200 and 200 px.'],
            'offset y' => [['offset_y' => 201], 'Offsets must be between -200 and 200 px.'],
        ];
    }

    public function testEveryPlacementIsChecked(): void
    {
        $label = $this->label([], [$this->placement(['area' => 'email']), $this->placement(['gap' => -1])]);

        self::assertCount(2, $this->validator->validate($label));
    }

    /**
     * @param array<string, mixed> $overrides
     * @param PlacementInterface[]|null $placements
     */
    private function label(array $overrides = [], ?array $placements = null): Label
    {
        /** @var Label $label */
        $label = $this->objectManager->getObject(Label::class);
        $label->setData(array_merge([
            'name' => 'Sale',
            'status' => 1,
            'priority' => 0,
            'design_id' => 1,
            'valid_from' => null,
            'valid_to' => null,
        ], $overrides));
        $label->setPlacements($placements ?? [$this->placement()]);

        return $label;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function placement(array $overrides = []): Placement
    {
        /** @var Placement $placement */
        $placement = $this->objectManager->getObject(Placement::class);
        $placement->setData(array_merge([
            'area' => 'listing',
            'position' => 'tl',
            'stacking' => 'vertical',
            'max_labels' => 3,
            'gap' => 4,
            'offset_x' => 0,
            'offset_y' => 0,
        ], $overrides));

        return $placement;
    }

    /**
     * @return string[]
     */
    private function messages(Label $label): array
    {
        return array_map('strval', $this->validator->validate($label));
    }
}
