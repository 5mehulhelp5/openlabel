<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Api\Data;

/**
 * A design: how a label looks. Reusable across labels. Text, alt text and tooltip are store-scoped.
 *
 * @api
 */
interface DesignInterface
{
    public const DESIGN_ID = 'design_id';
    public const NAME = 'name';
    public const TYPE = 'type';
    public const SHAPE = 'shape';
    public const IMAGE_PATH = 'image_path';
    public const IMAGE_WIDTH = 'image_width';
    public const IMAGE_HEIGHT = 'image_height';
    public const BG_COLOR = 'bg_color';
    public const TEXT_COLOR = 'text_color';
    public const BORDER_COLOR = 'border_color';
    public const BORDER_WIDTH = 'border_width';
    public const FONT_SIZE = 'font_size';
    public const SIZE_MODE = 'size_mode';
    public const WIDTH = 'width';
    public const HEIGHT = 'height';
    public const OPACITY = 'opacity';
    public const ROTATION = 'rotation';
    public const CUSTOM_CSS = 'custom_css';
    public const IS_SYSTEM = 'is_system';
    public const ANIMATION = 'animation';
    public const HOVER_EFFECT = 'hover_effect';
    public const SIZES = 'sizes';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    /** Store-scoped rows: [store_id => ['text' => ?, 'alt_text' => ?, 'tooltip' => ?]] */
    public const STORE_TEXTS = 'store_texts';

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_SHAPE = 'shape';
    public const TYPES = [self::TYPE_TEXT, self::TYPE_IMAGE, self::TYPE_SHAPE];

    public const SHAPES = ['rectangle', 'circle', 'ribbon', 'corner', 'pill'];

    public const SIZE_MODE_PERCENT = 'percent';
    public const SIZE_MODE_PX = 'px';

    public const DEFAULT_STORE_ID = 0;

    public const ANIMATION_NONE = 'none';
    public const ANIMATIONS = [self::ANIMATION_NONE, 'fade', 'pop'];

    public const HOVER_EFFECT_NONE = 'none';
    public const HOVER_EFFECTS = [self::HOVER_EFFECT_NONE, 'grow', 'lift'];

    /** Breakpoints with optional size overrides: small (phones), medium (tablets), large (desktops). */
    public const BREAKPOINTS = ['sm', 'md', 'lg'];

    /**
     * @return int|null
     */
    public function getDesignId(): ?int;

    /**
     * @param int $designId
     * @return $this
     */
    public function setDesignId(int $designId): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return string text|image|shape
     */
    public function getType(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * @return string|null rectangle|circle|ribbon|corner|pill
     */
    public function getShape(): ?string;

    /**
     * @param string|null $shape
     * @return $this
     */
    public function setShape(?string $shape): self;

    /**
     * @return string|null relative to pub/media/openlabel/designs
     */
    public function getImagePath(): ?string;

    /**
     * @param string|null $imagePath
     * @return $this
     */
    public function setImagePath(?string $imagePath): self;

    /**
     * @return int|null
     */
    public function getImageWidth(): ?int;

    /**
     * @param int|null $width
     * @return $this
     */
    public function setImageWidth(?int $width): self;

    /**
     * @return int|null
     */
    public function getImageHeight(): ?int;

    /**
     * @param int|null $height
     * @return $this
     */
    public function setImageHeight(?int $height): self;

    /**
     * @return string|null hex
     */
    public function getBgColor(): ?string;

    /**
     * @param string|null $color
     * @return $this
     */
    public function setBgColor(?string $color): self;

    /**
     * @return string|null hex
     */
    public function getTextColor(): ?string;

    /**
     * @param string|null $color
     * @return $this
     */
    public function setTextColor(?string $color): self;

    /**
     * @return string|null hex
     */
    public function getBorderColor(): ?string;

    /**
     * @param string|null $color
     * @return $this
     */
    public function setBorderColor(?string $color): self;

    /**
     * @return int px
     */
    public function getBorderWidth(): int;

    /**
     * @param int $width
     * @return $this
     */
    public function setBorderWidth(int $width): self;

    /**
     * @return int px, the clamp() maximum
     */
    public function getFontSize(): int;

    /**
     * @param int $fontSize
     * @return $this
     */
    public function setFontSize(int $fontSize): self;

    /**
     * @return string percent|px
     */
    public function getSizeMode(): string;

    /**
     * @param string $sizeMode
     * @return $this
     */
    public function setSizeMode(string $sizeMode): self;

    /**
     * @return int in size-mode units
     */
    public function getWidth(): int;

    /**
     * @param int $width
     * @return $this
     */
    public function setWidth(int $width): self;

    /**
     * @return int|null in size-mode units
     */
    public function getHeight(): ?int;

    /**
     * @param int|null $height
     * @return $this
     */
    public function setHeight(?int $height): self;

    /**
     * @return int 0..100
     */
    public function getOpacity(): int;

    /**
     * @param int $opacity
     * @return $this
     */
    public function setOpacity(int $opacity): self;

    /**
     * @return int degrees
     */
    public function getRotation(): int;

    /**
     * @param int $rotation
     * @return $this
     */
    public function setRotation(int $rotation): self;

    /**
     * @return string|null
     */
    public function getCustomCss(): ?string;

    /**
     * @param string|null $css
     * @return $this
     */
    public function setCustomCss(?string $css): self;

    /**
     * @return bool
     */
    public function isSystem(): bool;

    /**
     * @param bool $isSystem
     * @return $this
     */
    public function setIsSystem(bool $isSystem): self;

    /**
     * Entry animation, CSS only, off for visitors who prefer reduced motion (since 1.1).
     *
     * @return string one of ANIMATIONS
     */
    public function getAnimation(): string;

    /**
     * @param string $animation one of ANIMATIONS
     * @return $this
     */
    public function setAnimation(string $animation): self;

    /**
     * Effect while the shopper hovers the product card, CSS only (since 1.1).
     *
     * @return string one of HOVER_EFFECTS
     */
    public function getHoverEffect(): string;

    /**
     * @param string $effect one of HOVER_EFFECTS
     * @return $this
     */
    public function setHoverEffect(string $effect): self;

    /**
     * Per-breakpoint size overrides (since 1.1). Width is in the design's size mode; font size in px.
     *
     * UnstructuredArray (Magento's web API type for a free-form array) passes the map through as a JSON object, e.g. {"sm": {"width": 24, "font_size": 11}}.
     *
     * @return UnstructuredArray e.g. ['sm' => ['width' => 24, 'font_size' => 11]]
     * @phpstan-return array<string, array<string, int>>
     */
    public function getSizes(): array;

    /**
     * @param UnstructuredArray $sizes keys from BREAKPOINTS, values with width and/or font_size
     * @phpstan-param array<string, array<string, int>> $sizes
     * @return $this
     */
    public function setSizes(array $sizes): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Ignored: the database keeps the timestamps. Exists so an entity read from the web API can be sent back as is.
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): self;

    /**
     * Ignored: the database keeps the timestamps. Exists so an entity read from the web API can be sent back as is.
     *
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): self;

    /**
     * Label text for a store view, falling back to the default (store 0).
     *
     * @param int $storeId
     * @return string|null
     */
    public function getText(int $storeId = self::DEFAULT_STORE_ID): ?string;

    /**
     * @param string|null $text
     * @param int $storeId
     * @return $this
     */
    public function setText(?string $text, int $storeId = self::DEFAULT_STORE_ID): self;

    /**
     * Alternative text for a store view, falling back to the default (store 0).
     *
     * @param int $storeId
     * @return string|null
     */
    public function getAltText(int $storeId = self::DEFAULT_STORE_ID): ?string;

    /**
     * @param string|null $altText
     * @param int $storeId
     * @return $this
     */
    public function setAltText(?string $altText, int $storeId = self::DEFAULT_STORE_ID): self;

    /**
     * Tooltip for a store view, falling back to the default (store 0). Rendered from 1.1.
     *
     * @param int $storeId
     * @return string|null
     */
    public function getTooltip(int $storeId = self::DEFAULT_STORE_ID): ?string;

    /**
     * @param string|null $tooltip
     * @param int $storeId
     * @return $this
     */
    public function setTooltip(?string $tooltip, int $storeId = self::DEFAULT_STORE_ID): self;

    /**
     * All store-scoped rows: [store_id => ['text' => ?string, 'alt_text' => ?string, 'tooltip' => ?string]].
     * UnstructuredArray (Magento's web API type for a free-form array) passes the map through as a JSON object keyed by store ID.
     *
     * @return UnstructuredArray
     * @phpstan-return array<int, array<string, string|null>>
     */
    public function getStoreTexts(): array;

    /**
     * @param UnstructuredArray $storeTexts
     * @phpstan-param array<int, array<string, string|null>> $storeTexts
     * @return $this
     */
    public function setStoreTexts(array $storeTexts): self;
}
