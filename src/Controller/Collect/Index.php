<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Controller\Collect;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Inventory\PayloadParser;
use MeetMage\PaymentScriptGuard\Model\Inventory\Recorder;
use MeetMage\PaymentScriptGuard\Model\Token;
use Psr\Log\LoggerInterface;

/**
 * Receives script reports from payment pages (POST /meetmage_psg/collect).
 *
 * Requests are authenticated with the page token instead of a form key, because payment pages are
 * already open when the report is sent. Responses never reveal inventory details.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var PayloadParser
     */
    private PayloadParser $parser;

    /**
     * @var Token
     */
    private Token $token;

    /**
     * @var Recorder
     */
    private Recorder $recorder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param HttpRequest $request
     * @param JsonFactory $jsonFactory
     * @param Config $config
     * @param PayloadParser $parser
     * @param Token $token
     * @param Recorder $recorder
     * @param LoggerInterface $logger
     */
    public function __construct(
        HttpRequest $request,
        JsonFactory $jsonFactory,
        Config $config,
        PayloadParser $parser,
        Token $token,
        Recorder $recorder,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->parser = $parser;
        $this->token = $token;
        $this->recorder = $recorder;
        $this->logger = $logger;
    }

    /**
     * Record the report and tell the browser which inline sources it still has to send.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);
        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)->setData([]);
        }
        $report = $this->parser->parse((string) $this->request->getContent());
        if ($report === null || !$this->config->isPaymentPage($report['page'])
            || !$this->token->isValid($report['token'], $report['page'])
        ) {
            return $result->setHttpResponseCode(400)->setData([]);
        }
        try {
            $need = $this->recorder->record($report);
        } catch (\Exception $e) {
            $this->logger->error('[PaymentScriptGuard] Could not record report: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData([]);
        }
        return $result->setData(['need' => $need]);
    }

    /**
     * The page token replaces the form key check.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The page token replaces the form key check.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
