<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MeetMage\PaymentScriptGuard\Model\Integrity\Checker;

/**
 * Run the integrity check now instead of waiting for cron.
 */
class CheckAll extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MeetMage_PaymentScriptGuard::inventory';

    /**
     * @var Checker
     */
    private Checker $checker;

    /**
     * @param Context $context
     * @param Checker $checker
     */
    public function __construct(Context $context, Checker $checker)
    {
        parent::__construct($context);
        $this->checker = $checker;
    }

    /**
     * Check all scripts and report the result.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $stats = $this->checker->checkAll();
        $message = __(
            'Integrity check finished: %1 script file(s) checked, %2 changed, %3 could not be fetched.',
            $stats['checked'],
            $stats['changed'],
            $stats['unreachable']
        );
        if ($stats['changed'] > 0) {
            $this->messageManager->addWarningMessage($message);
        } else {
            $this->messageManager->addSuccessMessage($message);
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
