<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MeetMage\PaymentScriptGuard\Model\AdminIdentity;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script\CollectionFactory;
use MeetMage\PaymentScriptGuard\Model\Review;

/**
 * Reject the selected scripts.
 */
class MassReject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MeetMage_PaymentScriptGuard::authorize';

    /**
     * @var Filter
     */
    private Filter $filter;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var Review
     */
    private Review $review;

    /**
     * @var AdminIdentity
     */
    private AdminIdentity $adminIdentity;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param Review $review
     * @param AdminIdentity $adminIdentity
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Review $review,
        AdminIdentity $adminIdentity
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->review = $review;
        $this->adminIdentity = $adminIdentity;
    }

    /**
     * Reject every selected script.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $adminName = $this->adminIdentity->getName();
        $count = 0;
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            foreach ($collection->getAllIds() as $id) {
                $this->review->reject((int) $id, '', $adminName);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 script(s) rejected.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
