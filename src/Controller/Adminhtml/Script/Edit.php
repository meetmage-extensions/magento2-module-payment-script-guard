<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;

/**
 * Review page of one script.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MeetMage_PaymentScriptGuard::inventory';

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param ScriptResource $scriptResource
     */
    public function __construct(Context $context, PageFactory $pageFactory, ScriptResource $scriptResource)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->scriptResource = $scriptResource;
    }

    /**
     * Render the review page.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $connection = $this->scriptResource->getConnection();
        $exists = $id && $connection->fetchOne(
            $connection->select()->from($this->scriptResource->getMainTable(), 'script_id')->where('script_id = ?', $id)
        );
        if (!$exists) {
            $this->messageManager->addErrorMessage(__('This script no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('MeetMage_PaymentScriptGuard::inventory');
        $page->getConfig()->getTitle()->prepend((string) __('Review Script #%1', $id));
        return $page;
    }
}
