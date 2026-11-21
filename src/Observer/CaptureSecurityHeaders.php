<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Headers\HeaderMonitor;
use Psr\Log\LoggerInterface;

/**
 * Reads the security headers Magento sends on payment pages (controller_front_send_response_before).
 */
class CaptureSecurityHeaders implements ObserverInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var HeaderMonitor
     */
    private HeaderMonitor $headerMonitor;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param HeaderMonitor $headerMonitor
     * @param LoggerInterface $logger
     */
    public function __construct(Config $config, HeaderMonitor $headerMonitor, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->headerMonitor = $headerMonitor;
        $this->logger = $logger;
    }

    /**
     * Pass the response headers of monitored pages to the header monitor.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getData('request');
        $response = $observer->getEvent()->getData('response');
        if (!$request instanceof HttpRequest || !$response instanceof HttpResponse) {
            return;
        }
        if ($response->getHttpResponseCode() !== 200 || !$this->config->isHeaderWatchEnabled()
            || !$this->config->isEnabled()
        ) {
            return;
        }
        $page = (string) $request->getFullActionName();
        if (!$this->config->isPaymentPage($page)) {
            return;
        }
        try {
            $headers = [];
            foreach ($response->getHeaders() as $header) {
                $name = $header->getFieldName();
                $headers[$name] = isset($headers[$name])
                    ? $headers[$name] . ', ' . $header->getFieldValue()
                    : $header->getFieldValue();
            }
            $this->headerMonitor->observe($page, $headers);
        } catch (\Throwable $e) {
            $this->logger->error('[PaymentScriptGuard] Header check failed: ' . $e->getMessage());
        }
    }
}
