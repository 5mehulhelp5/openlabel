<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Controller\Adminhtml\Design;

use Iranimij\OpenLabel\Api\Data\DesignInterface;
use Iranimij\OpenLabel\Api\DesignRepositoryInterface;
use Iranimij\OpenLabel\Model\Css\Sanitizer;
use Iranimij\OpenLabel\Model\DesignFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;

/**
 * Saves the design form. Built-in designs are locked; custom CSS is only taken from users with its ACL resource
 * and is sanitized before it is stored.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenLabel::designs';
    public const PERSISTOR_KEY = 'openlabel_design';

    private const INT_FIELDS = [
        DesignInterface::BORDER_WIDTH => 0,
        DesignInterface::FONT_SIZE => 14,
        DesignInterface::WIDTH => 18,
        DesignInterface::OPACITY => 100,
        DesignInterface::ROTATION => 0,
    ];

    /**
     * @param Context $context
     * @param DesignRepositoryInterface $designRepository
     * @param DesignFactory $designFactory
     * @param Sanitizer $cssSanitizer
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly DesignFactory $designFactory,
        private readonly Sanitizer $cssSanitizer,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        /** @var array<string, mixed> $data */
        $data = (array) $request->getPostValue();
        $id = (int) ($data[DesignInterface::DESIGN_ID] ?? 0);
        try {
            $design = $id > 0 ? $this->designRepository->getById($id) : $this->designFactory->create();
            if ($design->isSystem()) {
                $this->messageManager->addErrorMessage(
                    (string) __('Built-in designs are locked. Use “Duplicate to edit” to make your own version.')
                );

                return $redirect->setPath('*/*/edit', ['id' => $id]);
            }
            $this->apply($design, $data);
            $this->designRepository->save($design);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage((string) __('The design was saved.'));
        } catch (ValidationException $e) {
            foreach ($e->getErrors() ?: [$e] as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }

            return $this->back($redirect, $id, $data);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->back($redirect, $id, $data);
        }

        if (($data['back'] ?? '') === 'edit') {
            return $redirect->setPath('*/*/edit', ['id' => $design->getDesignId()]);
        }

        return $redirect->setPath('*/*/');
    }

    /**
     * @param DesignInterface $design
     * @param array<string, mixed> $data
     * @return void
     */
    private function apply(DesignInterface $design, array $data): void
    {
        $design->setName(trim((string) ($data[DesignInterface::NAME] ?? '')))
            ->setType((string) ($data[DesignInterface::TYPE] ?? DesignInterface::TYPE_TEXT))
            ->setShape($this->nullable($data[DesignInterface::SHAPE] ?? null))
            ->setBgColor($this->nullable($data[DesignInterface::BG_COLOR] ?? null))
            ->setTextColor($this->nullable($data[DesignInterface::TEXT_COLOR] ?? null))
            ->setBorderColor($this->nullable($data[DesignInterface::BORDER_COLOR] ?? null))
            ->setSizeMode((string) ($data[DesignInterface::SIZE_MODE] ?? DesignInterface::SIZE_MODE_PERCENT))
            ->setAnimation((string) ($data[DesignInterface::ANIMATION] ?? '') ?: DesignInterface::ANIMATION_NONE)
            ->setHoverEffect((string) ($data[DesignInterface::HOVER_EFFECT] ?? '') ?: DesignInterface::HOVER_EFFECT_NONE)
            ->setSizes($this->sizes($data[DesignInterface::SIZES] ?? []));
        $design->setBorderWidth($this->int($data, DesignInterface::BORDER_WIDTH))
            ->setFontSize($this->int($data, DesignInterface::FONT_SIZE))
            ->setWidth($this->int($data, DesignInterface::WIDTH))
            ->setOpacity($this->int($data, DesignInterface::OPACITY))
            ->setRotation($this->int($data, DesignInterface::ROTATION));
        $height = $this->nullable($data[DesignInterface::HEIGHT] ?? null);
        $design->setHeight($height === null ? null : (int) $height);

        $image = $data['image'][0] ?? null;
        if (is_array($image) && !empty($image['file'])) {
            $design->setImagePath((string) $image['file'])
                ->setImageWidth(isset($image['width']) ? (int) $image['width'] : $design->getImageWidth())
                ->setImageHeight(isset($image['height']) ? (int) $image['height'] : $design->getImageHeight());
        } else {
            $design->setImagePath(null)->setImageWidth(null)->setImageHeight(null);
        }

        $design->setStoreTexts($this->storeTexts($data['store_texts'] ?? []));

        if ($this->_authorization->isAllowed('Iranimij_OpenLabel::custom_css')) {
            $design->setCustomCss($this->cssSanitizer->sanitize((string) ($data[DesignInterface::CUSTOM_CSS] ?? '')));
        }
    }

    /**
     * @param mixed $rows
     * @return array<int, array{text: ?string, alt_text: ?string, tooltip: ?string}>
     */
    private function storeTexts(mixed $rows): array
    {
        $texts = [];
        foreach (is_array($rows) ? $rows : [] as $storeId => $row) {
            $storeId = (int) $storeId;
            if (!is_array($row) || ($storeId !== 0 && (string) ($row['use_default'] ?? '0') === '1')) {
                continue;
            }
            $text = $this->nullable($row['text'] ?? null);
            $alt = $this->nullable($row['alt_text'] ?? null);
            if ($storeId !== 0 && $text === null && $alt === null) {
                continue;
            }
            $texts[$storeId] = ['text' => $text, 'alt_text' => $alt, 'tooltip' => null];
        }

        return $texts;
    }

    /**
     * Breakpoint overrides from the form: empty inputs are dropped, the validator checks the rest.
     *
     * @param mixed $rows breakpoint => [width, font_size]
     * @return array<string, array<string, int>>
     */
    private function sizes(mixed $rows): array
    {
        $sizes = [];
        foreach (is_array($rows) ? $rows : [] as $breakpoint => $row) {
            foreach (is_array($row) ? $row : [] as $field => $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $sizes[(string) $breakpoint][(string) $field] = (int) $value;
                }
            }
        }

        return $sizes;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $field
     * @return int
     */
    private function int(array $data, string $field): int
    {
        $value = trim((string) ($data[$field] ?? ''));

        return $value === '' ? self::INT_FIELDS[$field] : (int) $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param Redirect $redirect
     * @param int $id
     * @param array<string, mixed> $data
     * @return Redirect
     */
    private function back(Redirect $redirect, int $id, array $data): Redirect
    {
        $this->dataPersistor->set(self::PERSISTOR_KEY, $data);

        return $id > 0 ? $redirect->setPath('*/*/edit', ['id' => $id]) : $redirect->setPath('*/*/new');
    }
}
