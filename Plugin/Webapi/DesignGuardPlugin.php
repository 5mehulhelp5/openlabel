<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Plugin\Webapi;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;

/**
 * The rules the design form enforces in the admin, for designs saved or deleted through the REST and SOAP APIs
 * (registered in the webapi_rest and webapi_soap areas only; imports, setup patches and the CLI are not affected):
 * built-in designs are locked and new ones cannot claim to be built in, custom CSS needs its own permission and is
 * sanitized, and an image design can only point at an image uploaded in the admin.
 */
class DesignGuardPlugin
{
    public const CUSTOM_CSS_RESOURCE = 'Iranimij_OpenLabel::custom_css';

    private const IMAGE_PATH = '#^designs/(?:[A-Za-z0-9_][A-Za-z0-9._-]*/)*[A-Za-z0-9_][A-Za-z0-9._-]*$#';

    /**
     * @param AuthorizationInterface $authorization
     * @param Sanitizer $cssSanitizer
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly Sanitizer $cssSanitizer,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param DesignRepositoryInterface $subject
     * @param DesignInterface $design
     * @return array{DesignInterface}
     * @throws LocalizedException
     */
    public function beforeSave(DesignRepositoryInterface $subject, DesignInterface $design): array
    {
        $designId = (int) $design->getDesignId();
        $existing = $designId > 0 ? $subject->getById($designId) : null;
        if ($existing !== null && $existing->isSystem()) {
            throw $this->locked();
        }
        $design->setIsSystem(false);

        if ($this->authorization->isAllowed(self::CUSTOM_CSS_RESOURCE)) {
            $design->setCustomCss($this->cssSanitizer->sanitize($design->getCustomCss()));
        } else {
            $design->setCustomCss($existing?->getCustomCss());
        }

        $imagePath = $design->getImagePath();
        if ($imagePath !== null && $imagePath !== '' && !$this->isUploadedImage($imagePath)) {
            throw new LocalizedException(__(
                'Upload the image in the admin (Catalog > OpenLabel > Designs), then use its path, e.g. designs/sale.png.'
            ));
        }

        return [$design];
    }

    /**
     * @param DesignRepositoryInterface $subject
     * @param DesignInterface $design
     * @return null
     * @throws LocalizedException
     */
    public function beforeDelete(DesignRepositoryInterface $subject, DesignInterface $design)
    {
        if ($design->isSystem()) {
            throw $this->locked();
        }

        return null;
    }

    /**
     * @param DesignRepositoryInterface $subject
     * @param int $designId
     * @return null
     * @throws LocalizedException
     */
    public function beforeDeleteById(DesignRepositoryInterface $subject, int $designId)
    {
        if ($subject->getById($designId)->isSystem()) {
            throw $this->locked();
        }

        return null;
    }

    /**
     * @return LocalizedException
     */
    private function locked(): LocalizedException
    {
        return new LocalizedException(__('Built-in designs are locked. Create your own design instead.'));
    }

    /**
     * @param string $path relative to pub/media/openlabel
     * @return bool
     */
    private function isUploadedImage(string $path): bool
    {
        return preg_match(self::IMAGE_PATH, $path) === 1
            && $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile(ImageUrl::MEDIA_DIR . '/' . $path);
    }
}
