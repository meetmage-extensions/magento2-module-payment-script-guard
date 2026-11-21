<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Headers;

use Magento\Framework\FlagManager;
use MeetMage\PaymentScriptGuard\Model\Event;
use MeetMage\PaymentScriptGuard\Model\EventLogger;

/**
 * Keeps the last known security headers of each payment page and reports changes.
 */
class HeaderMonitor
{
    private const FLAG_PREFIX = 'meetmage_psg_headers_';

    /**
     * @var HeaderFingerprint
     */
    private HeaderFingerprint $fingerprint;

    /**
     * @var FlagManager
     */
    private FlagManager $flagManager;

    /**
     * @var EventLogger
     */
    private EventLogger $eventLogger;

    /**
     * @param HeaderFingerprint $fingerprint
     * @param FlagManager $flagManager
     * @param EventLogger $eventLogger
     */
    public function __construct(HeaderFingerprint $fingerprint, FlagManager $flagManager, EventLogger $eventLogger)
    {
        $this->fingerprint = $fingerprint;
        $this->flagManager = $flagManager;
        $this->eventLogger = $eventLogger;
    }

    /**
     * Compare the headers of a payment page response with the stored ones.
     *
     * @param string $page Full action name
     * @param array<string,string> $headers
     * @return void
     */
    public function observe(string $page, array $headers): void
    {
        $normalized = $this->fingerprint->normalize($headers);
        $hash = $this->fingerprint->hash($normalized);
        $flagCode = self::FLAG_PREFIX . substr(hash('sha256', $page), 0, 32);
        $stored = $this->flagManager->getFlagData($flagCode);

        if (!is_array($stored) || !isset($stored['hash'])) {
            $this->flagManager->saveFlag($flagCode, ['hash' => $hash, 'headers' => $normalized, 'page' => $page]);
            $this->eventLogger->log(
                Event::TYPE_HEADERS_BASELINE,
                Event::SEVERITY_INFO,
                sprintf('Security headers recorded for %s: %s', $page, $this->describe($normalized))
            );
            return;
        }
        if ($stored['hash'] === $hash) {
            return;
        }

        $this->flagManager->saveFlag($flagCode, ['hash' => $hash, 'headers' => $normalized, 'page' => $page]);
        $changes = $this->fingerprint->diff((array) ($stored['headers'] ?? []), $normalized);
        $this->eventLogger->log(
            Event::TYPE_HEADERS_CHANGED,
            Event::SEVERITY_WARNING,
            sprintf(
                'Security headers of payment page %s changed: %s. If you did not change your CSP or server '
                . 'configuration, check who did. Now: %s',
                $page,
                implode(', ', $changes) ?: 'values changed',
                $this->describe($normalized)
            )
        );
    }

    /**
     * Readable summary of the headers.
     *
     * @param array<string,string> $headers
     * @return string
     */
    private function describe(array $headers): string
    {
        if (!$headers) {
            return 'no security headers';
        }
        $parts = [];
        foreach ($headers as $name => $value) {
            $parts[] = $name . ': ' . (mb_strlen($value) > 300 ? mb_substr($value, 0, 299) . '…' : $value);
        }
        return implode(' | ', $parts);
    }
}
