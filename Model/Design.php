<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Model\ResourceModel\Design as DesignResource;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class Design extends AbstractModel implements DesignInterface, IdentityInterface
{
    public const CACHE_TAG = 'openlabel_design';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'openlabel_design';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(DesignResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG];
        if ($this->getDesignId() !== null) {
            $identities[] = self::CACHE_TAG . '_' . $this->getDesignId();
        }

        return $identities;
    }

    /**
     * @inheritDoc
     */
    public function getDesignId(): ?int
    {
        return $this->getData(self::DESIGN_ID) === null ? null : (int) $this->getData(self::DESIGN_ID);
    }

    /**
     * @inheritDoc
     */
    public function setDesignId(int $designId): DesignInterface
    {
        return $this->setData(self::DESIGN_ID, $designId);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): DesignInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return (string) ($this->getData(self::TYPE) ?? self::TYPE_TEXT);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): DesignInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getShape(): ?string
    {
        return $this->nullableString(self::SHAPE);
    }

    /**
     * @inheritDoc
     */
    public function setShape(?string $shape): DesignInterface
    {
        return $this->setData(self::SHAPE, $shape);
    }

    /**
     * @inheritDoc
     */
    public function getImagePath(): ?string
    {
        return $this->nullableString(self::IMAGE_PATH);
    }

    /**
     * @inheritDoc
     */
    public function setImagePath(?string $imagePath): DesignInterface
    {
        return $this->setData(self::IMAGE_PATH, $imagePath);
    }

    /**
     * @inheritDoc
     */
    public function getImageWidth(): ?int
    {
        return $this->nullableInt(self::IMAGE_WIDTH);
    }

    /**
     * @inheritDoc
     */
    public function setImageWidth(?int $width): DesignInterface
    {
        return $this->setData(self::IMAGE_WIDTH, $width);
    }

    /**
     * @inheritDoc
     */
    public function getImageHeight(): ?int
    {
        return $this->nullableInt(self::IMAGE_HEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setImageHeight(?int $height): DesignInterface
    {
        return $this->setData(self::IMAGE_HEIGHT, $height);
    }

    /**
     * @inheritDoc
     */
    public function getBgColor(): ?string
    {
        return $this->nullableString(self::BG_COLOR);
    }

    /**
     * @inheritDoc
     */
    public function setBgColor(?string $color): DesignInterface
    {
        return $this->setData(self::BG_COLOR, $color);
    }

    /**
     * @inheritDoc
     */
    public function getTextColor(): ?string
    {
        return $this->nullableString(self::TEXT_COLOR);
    }

    /**
     * @inheritDoc
     */
    public function setTextColor(?string $color): DesignInterface
    {
        return $this->setData(self::TEXT_COLOR, $color);
    }

    /**
     * @inheritDoc
     */
    public function getBorderColor(): ?string
    {
        return $this->nullableString(self::BORDER_COLOR);
    }

    /**
     * @inheritDoc
     */
    public function setBorderColor(?string $color): DesignInterface
    {
        return $this->setData(self::BORDER_COLOR, $color);
    }

    /**
     * @inheritDoc
     */
    public function getBorderWidth(): int
    {
        return (int) $this->getData(self::BORDER_WIDTH);
    }

    /**
     * @inheritDoc
     */
    public function setBorderWidth(int $width): DesignInterface
    {
        return $this->setData(self::BORDER_WIDTH, $width);
    }

    /**
     * @inheritDoc
     */
    public function getFontSize(): int
    {
        return $this->getData(self::FONT_SIZE) === null ? 14 : (int) $this->getData(self::FONT_SIZE);
    }

    /**
     * @inheritDoc
     */
    public function setFontSize(int $fontSize): DesignInterface
    {
        return $this->setData(self::FONT_SIZE, $fontSize);
    }

    /**
     * @inheritDoc
     */
    public function getSizeMode(): string
    {
        return (string) ($this->getData(self::SIZE_MODE) ?? self::SIZE_MODE_PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function setSizeMode(string $sizeMode): DesignInterface
    {
        return $this->setData(self::SIZE_MODE, $sizeMode);
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): int
    {
        return $this->getData(self::WIDTH) === null ? 18 : (int) $this->getData(self::WIDTH);
    }

    /**
     * @inheritDoc
     */
    public function setWidth(int $width): DesignInterface
    {
        return $this->setData(self::WIDTH, $width);
    }

    /**
     * @inheritDoc
     */
    public function getHeight(): ?int
    {
        return $this->nullableInt(self::HEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setHeight(?int $height): DesignInterface
    {
        return $this->setData(self::HEIGHT, $height);
    }

    /**
     * @inheritDoc
     */
    public function getOpacity(): int
    {
        return $this->getData(self::OPACITY) === null ? 100 : (int) $this->getData(self::OPACITY);
    }

    /**
     * @inheritDoc
     */
    public function setOpacity(int $opacity): DesignInterface
    {
        return $this->setData(self::OPACITY, $opacity);
    }

    /**
     * @inheritDoc
     */
    public function getRotation(): int
    {
        return (int) $this->getData(self::ROTATION);
    }

    /**
     * @inheritDoc
     */
    public function setRotation(int $rotation): DesignInterface
    {
        return $this->setData(self::ROTATION, $rotation);
    }

    /**
     * @inheritDoc
     */
    public function getCustomCss(): ?string
    {
        return $this->nullableString(self::CUSTOM_CSS);
    }

    /**
     * @inheritDoc
     */
    public function setCustomCss(?string $css): DesignInterface
    {
        return $this->setData(self::CUSTOM_CSS, $css);
    }

    /**
     * @inheritDoc
     */
    public function isSystem(): bool
    {
        return (bool) $this->getData(self::IS_SYSTEM);
    }

    /**
     * @inheritDoc
     */
    public function setIsSystem(bool $isSystem): DesignInterface
    {
        return $this->setData(self::IS_SYSTEM, $isSystem);
    }

    /**
     * @inheritDoc
     */
    public function getAnimation(): string
    {
        $value = (string) $this->getData(self::ANIMATION);

        return $value === '' ? self::ANIMATION_NONE : $value;
    }

    /**
     * @inheritDoc
     */
    public function setAnimation(string $animation): DesignInterface
    {
        return $this->setData(self::ANIMATION, $animation);
    }

    /**
     * @inheritDoc
     */
    public function getHoverEffect(): string
    {
        $value = (string) $this->getData(self::HOVER_EFFECT);

        return $value === '' ? self::HOVER_EFFECT_NONE : $value;
    }

    /**
     * @inheritDoc
     */
    public function setHoverEffect(string $effect): DesignInterface
    {
        return $this->setData(self::HOVER_EFFECT, $effect);
    }

    /**
     * @inheritDoc
     */
    public function getSizes(): array
    {
        $raw = $this->getData(self::SIZES);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }
        $sizes = [];
        foreach ($decoded as $breakpoint => $values) {
            if (is_array($values)) {
                $sizes[(string) $breakpoint] = array_map('intval', $values);
            }
        }

        return $sizes;
    }

    /**
     * @inheritDoc
     */
    public function setSizes(array $sizes): DesignInterface
    {
        $clean = [];
        foreach ($sizes as $breakpoint => $values) {
            $values = array_filter((array) $values, static fn ($value): bool => $value !== null && $value !== '');
            if ($values !== []) {
                $clean[(string) $breakpoint] = array_map('intval', $values);
            }
        }

        return $this->setData(self::SIZES, $clean === [] ? null : (string) json_encode($clean));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->nullableString(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->nullableString(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getText(int $storeId = self::DEFAULT_STORE_ID): ?string
    {
        return $this->storeValue('text', $storeId);
    }

    /**
     * @inheritDoc
     */
    public function setText(?string $text, int $storeId = self::DEFAULT_STORE_ID): DesignInterface
    {
        return $this->setStoreValue('text', $text, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getAltText(int $storeId = self::DEFAULT_STORE_ID): ?string
    {
        return $this->storeValue('alt_text', $storeId);
    }

    /**
     * @inheritDoc
     */
    public function setAltText(?string $altText, int $storeId = self::DEFAULT_STORE_ID): DesignInterface
    {
        return $this->setStoreValue('alt_text', $altText, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getTooltip(int $storeId = self::DEFAULT_STORE_ID): ?string
    {
        return $this->storeValue('tooltip', $storeId);
    }

    /**
     * @inheritDoc
     */
    public function setTooltip(?string $tooltip, int $storeId = self::DEFAULT_STORE_ID): DesignInterface
    {
        return $this->setStoreValue('tooltip', $tooltip, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getStoreTexts(): array
    {
        $rows = $this->getData(self::STORE_TEXTS);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @inheritDoc
     */
    public function setStoreTexts(array $storeTexts): DesignInterface
    {
        $normalized = [];
        foreach ($storeTexts as $storeId => $row) {
            $normalized[(int) $storeId] = [
                'text' => $row['text'] ?? null,
                'alt_text' => $row['alt_text'] ?? null,
                'tooltip' => $row['tooltip'] ?? null,
            ];
        }

        return $this->setData(self::STORE_TEXTS, $normalized);
    }

    /**
     * @param string $field
     * @param int $storeId
     * @return string|null
     */
    private function storeValue(string $field, int $storeId): ?string
    {
        $rows = $this->getStoreTexts();
        $value = $rows[$storeId][$field] ?? null;
        if (($value === null || $value === '') && $storeId !== self::DEFAULT_STORE_ID) {
            $value = $rows[self::DEFAULT_STORE_ID][$field] ?? null;
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param int $storeId
     * @return $this
     */
    private function setStoreValue(string $field, ?string $value, int $storeId): DesignInterface
    {
        $rows = $this->getStoreTexts();
        $rows[$storeId] = array_merge(['text' => null, 'alt_text' => null, 'tooltip' => null], $rows[$storeId] ?? []);
        $rows[$storeId][$field] = $value;

        return $this->setData(self::STORE_TEXTS, $rows);
    }

    /**
     * @param string $key
     * @return string|null
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->getData($key);

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param string $key
     * @return int|null
     */
    private function nullableInt(string $key): ?int
    {
        $value = $this->getData($key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
