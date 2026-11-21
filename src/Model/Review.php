<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\Integrity\Checker;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;

/**
 * Authorize or reject inventory entries (PCI DSS 4.0.1 requirement 6.4.3: each script is authorized,
 * with a written business justification).
 */
class Review
{
    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var Checker
     */
    private Checker $checker;

    /**
     * @var EventLogger
     */
    private EventLogger $eventLogger;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ScriptResource $scriptResource
     * @param Checker $checker
     * @param EventLogger $eventLogger
     * @param DateTime $dateTime
     */
    public function __construct(
        ScriptResource $scriptResource,
        Checker $checker,
        EventLogger $eventLogger,
        DateTime $dateTime
    ) {
        $this->scriptResource = $scriptResource;
        $this->checker = $checker;
        $this->eventLogger = $eventLogger;
        $this->dateTime = $dateTime;
    }

    /**
     * Authorize a script; the current version of a script file becomes its approved baseline.
     *
     * @param int $scriptId
     * @param string $justification
     * @param string $adminName
     * @param bool|null $allowChanges Null keeps the current setting
     * @return void
     * @throws LocalizedException
     */
    public function authorize(int $scriptId, string $justification, string $adminName, ?bool $allowChanges = null): void
    {
        $justification = trim($justification);
        if ($justification === '') {
            throw new LocalizedException(__('Enter a business justification to authorize the script.'));
        }
        $row = $this->load($scriptId);
        $data = [
            'status' => Script::STATUS_AUTHORIZED,
            'justification' => mb_substr($justification, 0, 2000),
            'authorized_by' => $adminName,
            'authorized_at' => $this->dateTime->gmtDate(),
        ];
        if ($allowChanges !== null) {
            $data['allow_changes'] = (int) $allowChanges;
        }
        $this->update($scriptId, $data);
        $this->eventLogger->log(
            Event::TYPE_AUTHORIZED,
            Event::SEVERITY_INFO,
            sprintf('%s authorized %s. Justification: %s', $adminName, $row['label'], $justification),
            $scriptId
        );
        if ($row['type'] === Script::TYPE_EXTERNAL) {
            $this->checker->approveCurrent($scriptId);
        }
    }

    /**
     * Reject a script: it should not run on payment pages, and it is reported if it appears again.
     *
     * @param int $scriptId
     * @param string $reason
     * @param string $adminName
     * @return void
     * @throws LocalizedException
     */
    public function reject(int $scriptId, string $reason, string $adminName): void
    {
        $row = $this->load($scriptId);
        $this->update($scriptId, [
            'status' => Script::STATUS_REJECTED,
            'justification' => trim($reason) !== '' ? mb_substr(trim($reason), 0, 2000) : null,
            'authorized_by' => $adminName,
            'authorized_at' => $this->dateTime->gmtDate(),
        ]);
        $this->eventLogger->log(
            Event::TYPE_REJECTED,
            Event::SEVERITY_INFO,
            sprintf('%s rejected %s.%s', $adminName, $row['label'], trim($reason) !== '' ? ' Reason: ' . $reason : ''),
            $scriptId
        );
    }

    /**
     * Inventory row.
     *
     * @param int $scriptId
     * @return array<string,mixed>
     * @throws LocalizedException
     */
    private function load(int $scriptId): array
    {
        $connection = $this->scriptResource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->scriptResource->getMainTable())->where('script_id = ?', $scriptId)
        );
        if (!$row) {
            throw new LocalizedException(__('This script no longer exists.'));
        }
        return $row;
    }

    /**
     * Update an inventory row.
     *
     * @param int $scriptId
     * @param array<string,mixed> $data
     * @return void
     */
    private function update(int $scriptId, array $data): void
    {
        $this->scriptResource->getConnection()->update(
            $this->scriptResource->getMainTable(),
            $data,
            ['script_id = ?' => $scriptId]
        );
    }
}
