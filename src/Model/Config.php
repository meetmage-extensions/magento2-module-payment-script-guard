<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the module's admin configuration (Stores > Configuration > MeetMage > Payment Script Guard).
 */
class Config
{
    public const XML_PATH_ENABLED = 'meetmage_paymentscriptguard/general/enabled';
    public const XML_PATH_PAGES = 'meetmage_paymentscriptguard/monitoring/pages';
    public const XML_PATH_SAMPLE_RATE = 'meetmage_paymentscriptguard/monitoring/sample_rate';
    public const XML_PATH_AUTO_FIRST_PARTY = 'meetmage_paymentscriptguard/monitoring/auto_authorize_first_party';
    public const XML_PATH_FIRST_PARTY_HOSTS = 'meetmage_paymentscriptguard/monitoring/first_party_hosts';
    public const XML_PATH_BULK_JUSTIFICATION = 'meetmage_paymentscriptguard/monitoring/bulk_justification';
    public const XML_PATH_INTEGRITY_ENABLED = 'meetmage_paymentscriptguard/integrity/enabled';
    public const XML_PATH_HEADERS_ENABLED = 'meetmage_paymentscriptguard/headers/enabled';
    public const XML_PATH_ALERTS_ENABLED = 'meetmage_paymentscriptguard/alerts/enabled';
    public const XML_PATH_ALERTS_RECIPIENTS = 'meetmage_paymentscriptguard/alerts/recipients';
    public const XML_PATH_ALERTS_IDENTITY = 'meetmage_paymentscriptguard/alerts/identity';
    public const XML_PATH_ALERTS_TEMPLATE = 'meetmage_paymentscriptguard/alerts/template';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Whether the module is enabled for the given store (current store when null).
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Full action names of the monitored payment pages.
     *
     * @return string[]
     */
    public function getPaymentPages(): array
    {
        return $this->lines((string) $this->scopeConfig->getValue(self::XML_PATH_PAGES));
    }

    /**
     * Whether the given full action name is a monitored payment page.
     *
     * @param string $fullActionName
     * @return bool
     */
    public function isPaymentPage(string $fullActionName): bool
    {
        return $fullActionName !== '' && in_array($fullActionName, $this->getPaymentPages(), true);
    }

    /**
     * Share of payment page visits that report their scripts, 1 to 100.
     *
     * @return int
     */
    public function getSampleRate(): int
    {
        $rate = (int) $this->scopeConfig->getValue(self::XML_PATH_SAMPLE_RATE);
        return max(1, min(100, $rate ?: 100));
    }

    /**
     * Whether script files from the store's own domains are authorized automatically.
     *
     * @return bool
     */
    public function isAutoAuthorizeFirstParty(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_AUTO_FIRST_PARTY);
    }

    /**
     * Extra hosts (for example a CDN) treated as first-party.
     *
     * @return string[]
     */
    public function getExtraFirstPartyHosts(): array
    {
        return array_map('strtolower', $this->lines((string) $this->scopeConfig->getValue(
            self::XML_PATH_FIRST_PARTY_HOSTS
        )));
    }

    /**
     * Justification recorded when scripts are authorized in bulk.
     *
     * @return string
     */
    public function getBulkJustification(): string
    {
        $text = trim((string) $this->scopeConfig->getValue(self::XML_PATH_BULK_JUSTIFICATION));
        return $text !== '' ? $text : 'Reviewed and authorized in bulk.';
    }

    /**
     * Whether the hourly integrity check runs.
     *
     * @return bool
     */
    public function isIntegrityCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INTEGRITY_ENABLED);
    }

    /**
     * Whether security headers of payment pages are watched.
     *
     * @return bool
     */
    public function isHeaderWatchEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HEADERS_ENABLED);
    }

    /**
     * Whether email alerts are sent.
     *
     * @return bool
     */
    public function isAlertsEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ALERTS_ENABLED);
    }

    /**
     * Alert recipients.
     *
     * @return string[]
     */
    public function getAlertRecipients(): array
    {
        $list = preg_split('/[\s,;]+/', (string) $this->scopeConfig->getValue(self::XML_PATH_ALERTS_RECIPIENTS));
        return array_values(array_filter(
            (array) $list,
            static fn ($email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /**
     * Email sender identity (general, sales, ...).
     *
     * @return string
     */
    public function getAlertIdentity(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_ALERTS_IDENTITY) ?: 'general';
    }

    /**
     * Email template ID for alerts.
     *
     * @return string
     */
    public function getAlertTemplate(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_ALERTS_TEMPLATE)
            ?: 'meetmage_paymentscriptguard_alerts_template';
    }

    /**
     * Non-empty, trimmed lines of a textarea value.
     *
     * @param string $value
     * @return string[]
     */
    private function lines(string $value): array
    {
        $lines = preg_split('/\R/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }
}
