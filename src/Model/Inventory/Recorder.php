<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Inventory;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Event;
use MeetMage\PaymentScriptGuard\Model\EventLogger;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Adds scripts reported by payment pages to the inventory and logs new or rejected ones.
 */
class Recorder
{
    /**
     * New inventory entries accepted per 24 hours from the public endpoint.
     */
    public const DAILY_NEW_LIMIT = 2000;

    public const FIRST_PARTY_JUSTIFICATION = 'First-party file served from the store\'s own domain '
        . '(authorized automatically; checked for changes against each deployment).';

    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var UrlNormalizer
     */
    private UrlNormalizer $urlNormalizer;

    /**
     * @var InlineFingerprint
     */
    private InlineFingerprint $inlineFingerprint;

    /**
     * @var FirstPartyHosts
     */
    private FirstPartyHosts $firstPartyHosts;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var EventLogger
     */
    private EventLogger $eventLogger;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var bool|null
     */
    private ?bool $limitReached = null;

    /**
     * @param ScriptResource $scriptResource
     * @param UrlNormalizer $urlNormalizer
     * @param InlineFingerprint $inlineFingerprint
     * @param FirstPartyHosts $firstPartyHosts
     * @param Config $config
     * @param EventLogger $eventLogger
     * @param DateTime $dateTime
     */
    public function __construct(
        ScriptResource $scriptResource,
        UrlNormalizer $urlNormalizer,
        InlineFingerprint $inlineFingerprint,
        FirstPartyHosts $firstPartyHosts,
        Config $config,
        EventLogger $eventLogger,
        DateTime $dateTime
    ) {
        $this->scriptResource = $scriptResource;
        $this->urlNormalizer = $urlNormalizer;
        $this->inlineFingerprint = $inlineFingerprint;
        $this->firstPartyHosts = $firstPartyHosts;
        $this->config = $config;
        $this->eventLogger = $eventLogger;
        $this->dateTime = $dateTime;
    }

    /**
     * Record a validated report; returns the inline hashes whose source the browser should send.
     *
     * @param array<string,mixed> $report Output of PayloadParser::parse()
     * @return string[]
     */
    public function record(array $report): array
    {
        $now = $this->dateTime->gmtDate();
        $page = $report['page'];
        $seen = [];

        foreach ($report['external'] as $url) {
            $id = $this->recordExternal($url, $page, $now);
            if ($id !== null) {
                $seen[] = $id;
            }
        }

        $known = $this->scriptResource->getKnownInlineHashes($report['inline']);
        foreach ($known as $scriptId) {
            $seen[] = $scriptId;
        }

        foreach ($report['contents'] as $content) {
            $id = $this->recordInline($content['source'], $content['hash'], $page, $now);
            if ($id !== null) {
                $seen[] = $id;
                $known[$content['hash']] = $id;
            }
        }

        $seen = array_values(array_unique($seen));
        $this->scriptResource->touch($seen, $page, $now);
        $this->reportRejected($seen);

        return array_values(array_diff($report['inline'], array_keys($known)));
    }

    /**
     * Inventory entry for an external script; creates it when new.
     *
     * @param string $url
     * @param string $page
     * @param string $now
     * @return int|null
     */
    private function recordExternal(string $url, string $page, string $now): ?int
    {
        $hosts = $this->firstPartyHosts->get();
        $normalized = $this->urlNormalizer->normalize($url, $hosts);
        if ($normalized === '') {
            return null;
        }
        $key = hash('sha256', 'external|' . $normalized);
        $id = $this->scriptResource->getIdByKey($key);
        if ($id !== null) {
            $this->scriptResource->getConnection()->update(
                $this->scriptResource->getMainTable(),
                ['url' => $url],
                ['script_id = ?' => $id]
            );
            return $id;
        }
        if ($this->isLimitReached()) {
            return null;
        }

        $host = $this->urlNormalizer->host($url);
        $firstParty = $this->urlNormalizer->isFirstParty($host, $hosts);
        $auto = $firstParty && $this->config->isAutoAuthorizeFirstParty();
        $id = $this->insert([
            'script_key' => $key,
            'type' => Script::TYPE_EXTERNAL,
            'label' => mb_substr($normalized, 0, 512),
            'url' => $url,
            'host' => $host !== '' ? $host : null,
            'first_party' => (int) $firstParty,
            'status' => $auto ? Script::STATUS_AUTHORIZED : Script::STATUS_NEW,
            'justification' => $auto ? self::FIRST_PARTY_JUSTIFICATION : null,
            'authorized_by' => $auto ? 'Payment Script Guard (automatic)' : null,
            'authorized_at' => $auto ? $now : null,
            'integrity_status' => str_starts_with($normalized, 'http')
                ? Script::INTEGRITY_PENDING
                : Script::INTEGRITY_NOT_APPLICABLE,
            'pages' => $page,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
        if ($id === null) {
            return $this->scriptResource->getIdByKey($key);
        }
        if ($auto) {
            $this->eventLogger->log(
                Event::TYPE_AUTO_AUTHORIZED,
                Event::SEVERITY_INFO,
                sprintf('First-party script added and authorized automatically: %s', $normalized),
                $id
            );
        } else {
            $this->eventLogger->log(
                Event::TYPE_NEW_SCRIPT,
                Event::SEVERITY_WARNING,
                sprintf('New script on payment page %s: %s. Review and authorize or reject it.', $page, $normalized),
                $id
            );
        }
        return $id;
    }

    /**
     * Inventory entry for an inline script; creates it when its fingerprint is new.
     *
     * @param string $source
     * @param string $rawHash
     * @param string $page
     * @param string $now
     * @return int|null
     */
    private function recordInline(string $source, string $rawHash, string $page, string $now): ?int
    {
        $key = hash('sha256', 'inline|' . $this->inlineFingerprint->fingerprint($source));
        $id = $this->scriptResource->getIdByKey($key);
        if ($id === null) {
            if ($this->isLimitReached()) {
                return null;
            }
            $preview = $this->inlineFingerprint->preview($source);
            $hosts = $this->inlineFingerprint->hosts($source);
            $id = $this->insert([
                'script_key' => $key,
                'type' => Script::TYPE_INLINE,
                'label' => mb_substr('Inline: ' . $preview, 0, 512),
                'content' => $source,
                'referenced_hosts' => $hosts ? implode("\n", $hosts) : null,
                'status' => Script::STATUS_NEW,
                'integrity_status' => Script::INTEGRITY_NOT_APPLICABLE,
                'pages' => $page,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
            if ($id === null) {
                $id = $this->scriptResource->getIdByKey($key);
            } else {
                $this->eventLogger->log(
                    Event::TYPE_NEW_SCRIPT,
                    Event::SEVERITY_WARNING,
                    sprintf(
                        'New inline script on payment page %s%s: "%s". Review and authorize or reject it.',
                        $page,
                        $hosts ? ' (references ' . implode(', ', $hosts) . ')' : '',
                        $this->inlineFingerprint->preview($source, 120)
                    ),
                    $id
                );
            }
        }
        if ($id !== null) {
            $this->scriptResource->rememberInlineHash($rawHash, $id, $now);
        }
        return $id;
    }

    /**
     * Critical alert when a rejected script is still being loaded (once a day per script).
     *
     * @param int[] $scriptIds
     * @return void
     */
    private function reportRejected(array $scriptIds): void
    {
        if (!$scriptIds) {
            return;
        }
        $connection = $this->scriptResource->getConnection();
        $rejected = $connection->fetchPairs(
            $connection->select()
                ->from($this->scriptResource->getMainTable(), ['script_id', 'label'])
                ->where('script_id IN (?)', $scriptIds)
                ->where('status = ?', Script::STATUS_REJECTED)
        );
        foreach ($rejected as $id => $label) {
            $this->eventLogger->logThrottled(
                Event::TYPE_REJECTED_SEEN,
                Event::SEVERITY_CRITICAL,
                sprintf('A rejected script is still running on your payment page: %s. Remove it from the '
                    . 'theme or module that adds it, or check the store for compromise.', $label),
                (int) $id
            );
        }
    }

    /**
     * Insert a row; returns the new ID, or null when another request inserted it first.
     *
     * @param array<string,mixed> $row
     * @return int|null
     */
    private function insert(array $row): ?int
    {
        $connection = $this->scriptResource->getConnection();
        if (!$connection) {
            return null;
        }
        try {
            $connection->insert($this->scriptResource->getMainTable(), $row);
        } catch (\Magento\Framework\DB\Adapter\DuplicateException $e) {
            return null;
        }
        return $this->scriptResource->getIdByKey((string) $row['script_key']);
    }

    /**
     * Whether the daily limit for new entries is reached (protects the inventory from report flooding).
     *
     * @return bool
     */
    private function isLimitReached(): bool
    {
        if ($this->limitReached === null) {
            $since = $this->dateTime->gmtDate('Y-m-d H:i:s', time() - 86400);
            $this->limitReached = $this->scriptResource->countCreatedSince($since) >= self::DAILY_NEW_LIMIT;
            if ($this->limitReached) {
                $this->eventLogger->logThrottled(
                    Event::TYPE_LIMIT_REACHED,
                    Event::SEVERITY_WARNING,
                    sprintf(
                        'More than %d new scripts were reported in 24 hours; new reports are ignored until '
                        . 'the count drops. This can mean someone is flooding the report endpoint.',
                        self::DAILY_NEW_LIMIT
                    ),
                    null
                );
            }
        }
        return $this->limitReached;
    }
}
