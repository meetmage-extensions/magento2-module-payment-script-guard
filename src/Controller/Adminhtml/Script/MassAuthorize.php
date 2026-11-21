<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MeetMage\PaymentScriptGuard\Model\AdminIdentity;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script\CollectionFactory;
use MeetMage\PaymentScriptGuard\Model\Review;

/**
 * Authorize the selected scripts with the configured bulk justification.
 */
class MassAuthorize extends Action implements HttpPostActionInterface
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
     * @var Config
     */
    private Config $config;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param Review $review
     * @param AdminIdentity $adminIdentity
     * @param Config $config
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Review $review,
        AdminIdentity $adminIdentity,
        Config $config
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->review = $review;
        $this->adminIdentity = $adminIdentity;
        $this->config = $config;
    }

    /**
     * Authorize every selected script.
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
                $this->review->authorize((int) $id, $this->config->getBulkJustification(), $adminName);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 script(s) authorized.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
