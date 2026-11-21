<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;

/**
 * Removes stale inline-hash cache entries. The inventory and the activity log are kept,
 * because they are the evidence for your PCI assessment.
 */
class Cleanup
{
    private const CACHE_DAYS = 30;

    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ScriptResource $scriptResource
     * @param DateTime $dateTime
     */
    public function __construct(ScriptResource $scriptResource, DateTime $dateTime)
    {
        $this->scriptResource = $scriptResource;
        $this->dateTime = $dateTime;
    }

    /**
     * Delete inline hashes not seen for 30 days.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->scriptResource->pruneInlineCache(
            $this->dateTime->gmtDate('Y-m-d H:i:s', time() - self::CACHE_DAYS * 86400)
        );
    }
}
