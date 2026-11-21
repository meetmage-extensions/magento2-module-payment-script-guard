<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Event as EventResource;
use Psr\Log\LoggerInterface;

/**
 * Writes the activity log. Info events are only logged; warnings and critical events are emailed.
 */
class EventLogger
{
    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param EventResource $eventResource
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(EventResource $eventResource, DateTime $dateTime, LoggerInterface $logger)
    {
        $this->eventResource = $eventResource;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Log an event.
     *
     * @param string $type One of the Event::TYPE_* constants
     * @param string $severity One of the Event::SEVERITY_* constants
     * @param string $message
     * @param int|null $scriptId
     * @return void
     */
    public function log(string $type, string $severity, string $message, ?int $scriptId = null): void
    {
        $now = $this->dateTime->gmtDate();
        try {
            $this->eventResource->getConnection()->insert($this->eventResource->getMainTable(), [
                'script_id' => $scriptId,
                'type' => $type,
                'severity' => $severity,
                'message' => mb_substr($message, 0, 4000),
                'created_at' => $now,
                // Info events need no alert: mark them as handled right away.
                'notified_at' => $severity === Event::SEVERITY_INFO ? $now : null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[PaymentScriptGuard] Could not log event: ' . $e->getMessage());
        }
        if ($severity === Event::SEVERITY_CRITICAL) {
            $this->logger->critical('[PaymentScriptGuard] ' . $message);
        }
    }

    /**
     * Log an event at most once per script and type within the given number of hours.
     *
     * @param string $type
     * @param string $severity
     * @param string $message
     * @param int|null $scriptId
     * @param int $hours
     * @return void
     */
    public function logThrottled(
        string $type,
        string $severity,
        string $message,
        ?int $scriptId,
        int $hours = 24
    ): void {
        $since = $this->dateTime->gmtDate('Y-m-d H:i:s', time() - $hours * 3600);
        if (!$this->eventResource->existsSince($type, $scriptId, $since)) {
            $this->log($type, $severity, $message, $scriptId);
        }
    }
}
