<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Cron;

use MeetMage\PaymentScriptGuard\Model\Alert\Sender;

/**
 * Emails pending alerts every 10 minutes.
 */
class SendAlerts
{
    /**
     * @var Sender
     */
    private Sender $sender;

    /**
     * @param Sender $sender
     */
    public function __construct(Sender $sender)
    {
        $this->sender = $sender;
    }

    /**
     * Send pending alerts.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->sender->sendPending();
    }
}
