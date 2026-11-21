<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Inventory;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\UrlParts;

/**
 * Hosts that belong to the store: base, secure, static and media URLs of every store view,
 * plus extra hosts from the configuration (for example a CDN).
 */
class FirstPartyHosts
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var UrlParts
     */
    private UrlParts $urlParts;

    /**
     * @var string[]|null
     */
    private ?array $hosts = null;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param UrlParts $urlParts
     */
    public function __construct(StoreManagerInterface $storeManager, Config $config, UrlParts $urlParts)
    {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->urlParts = $urlParts;
    }

    /**
     * Lower-case host names.
     *
     * @return string[]
     */
    public function get(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }
        $hosts = $this->config->getExtraFirstPartyHosts();
        $types = [UrlInterface::URL_TYPE_WEB, UrlInterface::URL_TYPE_STATIC, UrlInterface::URL_TYPE_MEDIA];
        foreach ($this->storeManager->getStores() as $store) {
            foreach ($types as $type) {
                foreach ([false, true] as $secure) {
                    try {
                        $host = $this->urlParts->host((string) $store->getBaseUrl($type, $secure));
                    } catch (\Exception $e) {
                        $host = '';
                    }
                    if ($host !== '') {
                        $hosts[] = $host;
                    }
                }
            }
        }
        $this->hosts = array_values(array_unique($hosts));
        return $this->hosts;
    }
}
