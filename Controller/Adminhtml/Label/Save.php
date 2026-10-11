<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Controller\Adminhtml\Label;

use Iranimij\OpenLabel\Api\LabelRepositoryInterface;
use Iranimij\OpenLabel\Model\Indexer\LabelIndexingException;
use Iranimij\OpenLabel\Model\Label\Duplicator;
use Iranimij\OpenLabel\Model\Label\FormMapper;
use Iranimij\OpenLabel\Model\LabelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Saves the label form (Save, Save & Continue, Save & Duplicate). The repository reindexes the label on save.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenLabel::labels';
    public const PERSISTOR_KEY = 'openlabel_label';

    /**
     * @param Context $context
     * @param LabelRepositoryInterface $labelRepository
     * @param LabelFactory $labelFactory
     * @param FormMapper $formMapper
     * @param Duplicator $duplicator
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface $logger openlabel.log
     */
    public function __construct(
        Context $context,
        private readonly LabelRepositoryInterface $labelRepository,
        private readonly LabelFactory $labelFactory,
        private readonly FormMapper $formMapper,
        private readonly Duplicator $duplicator,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
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
        $id = (int) ($data['label_id'] ?? 0);
        try {
            $label = $id > 0 ? $this->labelRepository->getById($id) : $this->labelFactory->create();
            $data += ['placements' => []];
            $this->formMapper->apply($label, $data);
            $label = $this->labelRepository->save($label);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage((string) __('The label was saved.'));
        } catch (LabelIndexingException $e) {
            // Saved, but its products could not be matched (logged): stay on the label so it can be fixed.
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $e->getLabelId()]);
        } catch (ValidationException $e) {
            foreach ($e->getErrors() ?: [$e] as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }

            return $this->back($redirect, $id, $data);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->back($redirect, $id, $data);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Saving label #%d failed: %s', $id, $e->getMessage()),
                ['label_id' => $id, 'exception' => $e]
            );
            $this->messageManager->addErrorMessage((string) __(
                'The label could not be saved: %1 Details are in var/log/openlabel.log.',
                $e->getMessage()
            ));

            return $this->back($redirect, $id, $data);
        }

        $back = (string) ($data['back'] ?? '');
        if ($back === 'duplicate') {
            $copy = $this->duplicator->duplicate($label);
            $this->messageManager->addSuccessMessage((string) __('A disabled copy was created. Change it and switch it on.'));

            return $redirect->setPath('*/*/edit', ['id' => $copy->getLabelId()]);
        }
        if ($back === 'edit') {
            return $redirect->setPath('*/*/edit', ['id' => $label->getLabelId()]);
        }

        return $redirect->setPath('*/*/');
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
