<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Image;

use Iranimij\OpenLabel\Model\Config;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Checks an uploaded label image: extension allow-list, real content type, size limits (warn over 50 KB,
 * refuse over the configured maximum, 1 MB by default), intrinsic dimensions (stored for CLS-free rendering, FE6).
 * SVGs are sanitized. AVIF is recognised by its `ftyp` brand and sized from its `ispe` property, so it does not
 * depend on the server's image libraries.
 */
class Validator
{
    public const WARN_BYTES = 50 * 1024;

    /** Extension => accepted MIME types. */
    private const TYPES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif', 'image/heif', 'application/octet-stream'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
    ];

    /**
     * @param SvgSanitizer $svgSanitizer
     * @param File $fileDriver
     * @param Config $config
     */
    public function __construct(
        private readonly SvgSanitizer $svgSanitizer,
        private readonly File $fileDriver,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $path uploaded temporary file
     * @param string $originalName file name as sent by the browser
     * @return ValidationResult
     */
    public function validate(string $path, string $originalName): ValidationResult
    {
        $dot = strrpos($originalName, '.');
        $extension = $dot === false ? '' : strtolower(substr($originalName, $dot + 1));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        if (!isset(self::TYPES[$extension])) {
            return new ValidationResult([__('Allowed image types are PNG, JPG, GIF, WebP, AVIF and SVG.')]);
        }
        try {
            $size = (int) ($this->fileDriver->stat($path)['size'] ?? 0);
            $contents = (string) $this->fileDriver->fileGetContents($path);
        } catch (FileSystemException $e) {
            return $this->mismatch();
        }
        $maxKb = $this->config->getMaxUploadKb();
        if ($size > $maxKb * 1024) {
            $limit = $maxKb % 1024 === 0 ? ($maxKb / 1024) . ' MB' : $maxKb . ' KB';

            return new ValidationResult([__('The image is larger than %1. Use a smaller file; SVG works best.', $limit)]);
        }
        $warnings = $size > self::WARN_BYTES
            ? [__('The image is larger than 50 KB (%1 KB). Smaller files keep listing pages fast.', (int) ceil($size / 1024))]
            : [];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, self::TYPES[$extension], true)) {
            return $this->mismatch();
        }

        if ($extension === 'svg') {
            try {
                $clean = $this->svgSanitizer->sanitize($contents);
            } catch (LocalizedException $e) {
                return new ValidationResult([$e->getRawMessage() === '' ? __('The file is not a valid SVG image.') : __($e->getRawMessage())]);
            }
            [$width, $height] = $this->svgSanitizer->dimensions($clean);

            return new ValidationResult([], $warnings, 'svg', $width, $height, $clean);
        }

        if ($extension === 'avif') {
            [$width, $height] = $this->avifDimensions($contents);

            return $width > 0 && $height > 0
                ? new ValidationResult([], $warnings, 'avif', $width, $height)
                : $this->mismatch();
        }

        $info = $contents === '' ? false : getimagesizefromstring($contents);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return $this->mismatch();
        }

        return new ValidationResult([], $warnings, $extension, (int) $info[0], (int) $info[1]);
    }

    /**
     * Width and height of an AVIF file: the `ftyp` box must name the avif (still) or avis (sequence) brand, the
     * first `ispe` (image spatial extents) property holds the size.
     *
     * @param string $contents
     * @return array{int, int} [0, 0] when the file is not an AVIF image
     */
    private function avifDimensions(string $contents): array
    {
        if (substr($contents, 4, 4) !== 'ftyp' || !in_array(substr($contents, 8, 4), ['avif', 'avis'], true)) {
            return [0, 0];
        }
        $ispe = strpos($contents, 'ispe');
        if ($ispe === false || strlen($contents) < $ispe + 16) {
            return [0, 0];
        }

        return [$this->uint32($contents, $ispe + 8), $this->uint32($contents, $ispe + 12)];
    }

    /**
     * @param string $bytes
     * @param int $offset
     * @return int big-endian unsigned 32-bit integer at the offset
     */
    private function uint32(string $bytes, int $offset): int
    {
        $value = 0;
        for ($i = 0; $i < 4; $i++) {
            $value = ($value << 8) | ord($bytes[$offset + $i]);
        }

        return $value;
    }

    /**
     * @return ValidationResult
     */
    private function mismatch(): ValidationResult
    {
        return new ValidationResult([
            __('The file content does not match its extension. Upload a real PNG, JPG, GIF, WebP, AVIF or SVG image.'),
        ]);
    }
}
