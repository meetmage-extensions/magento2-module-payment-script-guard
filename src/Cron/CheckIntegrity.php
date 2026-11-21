<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Cron;

use MeetMage\PaymentScriptGuard\Model\Config;
use MeetMage\PaymentScriptGuard\Model\Integrity\Checker;

/**
 * Hourly integrity check of payment page scripts.
 */
class CheckIntegrity
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Checker
     */
    private Checker $checker;

    /**
     * @param Config $config
     * @param Checker $checker
     */
    public function __construct(Config $config, Checker $checker)
    {
        $this->config = $config;
        $this->checker = $checker;
    }

    /**
     * Run the check when enabled.
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->config->isIntegrityCheckEnabled()) {
            $this->checker->checkAll();
        }
    }
}
