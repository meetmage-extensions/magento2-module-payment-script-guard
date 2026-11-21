<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Adminhtml\Script;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use MeetMage\PaymentScriptGuard\Model\Report\CsvReport;

/**
 * Download the inventory as CSV (evidence for PCI DSS requirement 6.4.3).
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MeetMage_PaymentScriptGuard::inventory';

    /**
     * @var FileFactory
     */
    private FileFactory $fileFactory;

    /**
     * @var CsvReport
     */
    private CsvReport $report;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param CsvReport $report
     */
    public function __construct(Context $context, FileFactory $fileFactory, CsvReport $report)
    {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->report = $report;
    }

    /**
     * Send the CSV file.
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        return $this->fileFactory->create(
            'payment-script-inventory-' . gmdate('Y-m-d') . '.csv',
            $this->report->build(),
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
