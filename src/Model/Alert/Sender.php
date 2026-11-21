<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Alert;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\Store;
use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Event;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Event as EventResource;
use Psr\Log\LoggerInterface;

/**
 * Emails pending warnings and critical events as one digest.
 */
class Sender
{
    private const BATCH = 200;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var TransportBuilder
     */
    private TransportBuilder $transportBuilder;

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * @var BackendUrl
     */
    private BackendUrl $backendUrl;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param EventResource $eventResource
     * @param TransportBuilder $transportBuilder
     * @param Escaper $escaper
     * @param BackendUrl $backendUrl
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        EventResource $eventResource,
        TransportBuilder $transportBuilder,
        Escaper $escaper,
        BackendUrl $backendUrl,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->eventResource = $eventResource;
        $this->transportBuilder = $transportBuilder;
        $this->escaper = $escaper;
        $this->backendUrl = $backendUrl;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Send pending alerts; returns the number of events included in the email.
     *
     * @return int
     */
    public function sendPending(): int
    {
        $events = $this->eventResource->getPending(self::BATCH);
        if (!$events) {
            return 0;
        }
        $ids = array_map(static fn (array $e): int => (int) $e['event_id'], $events);
        $recipients = $this->config->getAlertRecipients();
        if (!$this->config->isAlertsEnabled() || !$recipients) {
            // Nobody to tell: keep the log, but do not let the queue grow forever.
            $this->eventResource->markNotified($ids, $this->dateTime->gmtDate());
            return 0;
        }

        $critical = count(array_filter($events, static fn (array $e): bool =>
            $e['severity'] === Event::SEVERITY_CRITICAL));
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($this->config->getAlertTemplate())
                ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars([
                    'subject_prefix' => $critical ? 'CRITICAL: ' : '',
                    'count' => count($events),
                    'events_html' => $this->renderEvents($events),
                    'inventory_url' => $this->backendUrl->getUrl('meetmage_psg/script/index', ['_nosecret' => true]),
                ])
                ->setFromByScope($this->config->getAlertIdentity(), Store::DEFAULT_STORE_ID);
            foreach ($recipients as $email) {
                $this->transportBuilder->addTo($email);
            }
            $this->transportBuilder->getTransport()->sendMessage();
        } catch (\Exception $e) {
            // Leave the events queued; the next run retries.
            $this->logger->error('[PaymentScriptGuard] Could not send alert email: ' . $e->getMessage());
            return 0;
        }
        $this->eventResource->markNotified($ids, $this->dateTime->gmtDate());
        return count($events);
    }

    /**
     * HTML list of events for the email body.
     *
     * @param array<int,array<string,mixed>> $events
     * @return string
     */
    private function renderEvents(array $events): string
    {
        $colors = [Event::SEVERITY_CRITICAL => '#b91c1c', Event::SEVERITY_WARNING => '#b45309'];
        $html = '';
        foreach ($events as $event) {
            $severity = (string) $event['severity'];
            $html .= sprintf(
                '<tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;vertical-align:top;white-space:nowrap;'
                . 'color:%s;font-weight:bold">%s</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;'
                . 'vertical-align:top;white-space:nowrap;color:#6b7280">%s UTC</td>'
                . '<td style="padding:8px;border-bottom:1px solid #e5e7eb">%s</td></tr>',
                $colors[$severity] ?? '#374151',
                $this->escaper->escapeHtml(strtoupper($severity)),
                $this->escaper->escapeHtml((string) $event['created_at']),
                $this->escaper->escapeHtml((string) $event['message'])
            );
        }
        return '<table style="border-collapse:collapse;width:100%;font-size:13px">' . $html . '</table>';
    }
}
