<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use MeetMage\PaymentScriptGuard\Model\AdminIdentity;
use MeetMage\PaymentScriptGuard\Model\Integrity\Checker;
use MeetMage\PaymentScriptGuard\Model\Review;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Save the decision for one script.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MeetMage_PaymentScriptGuard::authorize';

    /**
     * @var Review
     */
    private Review $review;

    /**
     * @var AdminIdentity
     */
    private AdminIdentity $adminIdentity;

    /**
     * @var Checker
     */
    private Checker $checker;

    /**
     * @param Context $context
     * @param Review $review
     * @param AdminIdentity $adminIdentity
     * @param Checker $checker
     */
    public function __construct(Context $context, Review $review, AdminIdentity $adminIdentity, Checker $checker)
    {
        parent::__construct($context);
        $this->review = $review;
        $this->adminIdentity = $adminIdentity;
        $this->checker = $checker;
    }

    /**
     * Authorize, reject or re-check the script.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $id = (int) $this->getRequest()->getParam('id');
        $decision = (string) $this->getRequest()->getParam('decision');
        $justification = (string) $this->getRequest()->getParam('justification');
        $adminName = $this->adminIdentity->getName();
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $id]);

        try {
            if ($decision === Script::STATUS_AUTHORIZED) {
                $this->review->authorize(
                    $id,
                    $justification,
                    $adminName,
                    (bool) $this->getRequest()->getParam('allow_changes')
                );
                $this->messageManager->addSuccessMessage((string) __('The script is authorized.'));
            } elseif ($decision === Script::STATUS_REJECTED) {
                $this->review->reject($id, $justification, $adminName);
                $this->messageManager->addSuccessMessage((string) __(
                    'The script is rejected. Remove it from the theme or module that adds it; you will be alerted '
                    . 'if it still runs on a payment page.'
                ));
            } elseif ($decision === 'check') {
                $status = $this->checker->checkOne($id);
                $this->messageManager->addNoticeMessage((string) __('Integrity check finished: %1.', $status));
            } else {
                throw new LocalizedException(__('Choose what to do with the script.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
