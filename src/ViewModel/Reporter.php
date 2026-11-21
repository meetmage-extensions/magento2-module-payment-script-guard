<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\ViewModel;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Token;

/**
 * Data for the script reporter rendered on payment pages.
 */
class Reporter implements ArgumentInterface
{
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Token
     */
    private Token $token;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Json
     */
    private Json $json;

    /**
     * @param HttpRequest $request
     * @param Config $config
     * @param Token $token
     * @param UrlInterface $urlBuilder
     * @param Json $json
     */
    public function __construct(
        HttpRequest $request,
        Config $config,
        Token $token,
        UrlInterface $urlBuilder,
        Json $json
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->token = $token;
        $this->urlBuilder = $urlBuilder;
        $this->json = $json;
    }

    /**
     * Whether the reporter runs on the current page.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->config->isEnabled()
            && $this->config->isPaymentPage((string) $this->request->getFullActionName());
    }

    /**
     * Reporter settings as JSON.
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        $page = (string) $this->request->getFullActionName();
        return (string) $this->json->serialize([
            'url' => $this->urlBuilder->getUrl('meetmage_psg/collect', ['_secure' => $this->request->isSecure()]),
            'token' => $this->token->create($page),
            'page' => $page,
            'sample' => $this->config->getSampleRate(),
        ]);
    }
}
