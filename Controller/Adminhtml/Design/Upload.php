<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Controller\Adminhtml\Design;

use Iranimij\OpenLabel\Model\Design\ImageUrl;
use Iranimij\OpenLabel\Model\Image\Validator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\File\UploaderFactory;

/**
 * Image upload for the design form (file-uploader UI component). Stores under pub/media/openlabel/designs/
 * after validation; SVGs are replaced by their sanitized markup. Returns the image's intrinsic size.
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenLabel::designs';

    private const TARGET = ImageUrl::MEDIA_DIR . '/designs';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param UploaderFactory $uploaderFactory
     * @param Validator $validator
     * @param Filesystem $filesystem
     * @param ImageUrl $imageUrl
     * @param File $fileDriver
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Validator $validator,
        private readonly Filesystem $filesystem,
        private readonly ImageUrl $imageUrl,
        private readonly File $fileDriver
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $json = $this->jsonFactory->create();
        try {
            $fileId = (string) $this->getRequest()->getParam('param_name', 'image');
            // The uploader's constructor rejects anything that is not a real upload in an allowed temp folder.
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
            /** @var \Magento\Framework\App\Request\Http $request */
            $request = $this->getRequest();
            $file = (array) $request->getFiles($fileId);
            $check = $this->validator->validate((string) $file['tmp_name'], (string) $file['name']);
            if ($check->getErrors() !== []) {
                return $json->setData(['error' => (string) $check->getErrors()[0], 'errorcode' => 1]);
            }
            if ($check->getSanitizedContents() !== null) {
                $this->fileDriver->filePutContents((string) $file['tmp_name'], $check->getSanitizedContents());
            }
            $uploader->setAllowedExtensions(['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $saved = $uploader->save($media->getAbsolutePath(self::TARGET));
            if (!$saved) {
                return $json->setData(['error' => (string) __('The image could not be saved.'), 'errorcode' => 1]);
            }
            $path = 'designs/' . ltrim((string) $saved['file'], '/');

            return $json->setData([
                'name' => (string) $saved['name'],
                'file' => $path,
                'url' => $this->imageUrl->get($path),
                'size' => (int) ($media->stat(ImageUrl::MEDIA_DIR . '/' . $path)['size'] ?? 0),
                'type' => (string) ($file['type'] ?? ''),
                'width' => $check->getWidth(),
                'height' => $check->getHeight(),
                'warnings' => array_map('strval', $check->getWarnings()),
            ]);
        } catch (\Exception $e) {
            return $json->setData(['error' => $e->getMessage(), 'errorcode' => $e->getCode() ?: 1]);
        }
    }
}
