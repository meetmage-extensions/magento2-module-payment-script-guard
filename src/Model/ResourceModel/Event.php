<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for the activity log.
 */
class Event extends AbstractDb
{
    public const TABLE = 'meetmage_psg_event';

    /**
     * Initialize the main table.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'event_id');
    }

    /**
     * Whether an event of this type was logged for the script since the given time.
     *
     * @param string $type
     * @param int|null $scriptId
     * @param string $since
     * @return bool
     */
    public function existsSince(string $type, ?int $scriptId, string $since): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'event_id')
            ->where('type = ?', $type)
            ->where('created_at >= ?', $since)
            ->limit(1);
        if ($scriptId !== null) {
            $select->where('script_id = ?', $scriptId);
        }
        return (bool) $connection->fetchOne($select);
    }

    /**
     * Events not processed by the alert job yet, oldest first.
     *
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getPending(int $limit): array
    {
        $connection = $this->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from(['e' => $this->getMainTable()])
                ->joinLeft(
                    ['s' => $this->getTable(Script::TABLE)],
                    's.script_id = e.script_id',
                    ['script_label' => 's.label']
                )
                ->where('e.notified_at IS NULL')
                ->order('e.event_id ASC')
                ->limit($limit)
        );
    }

    /**
     * Mark events as processed by the alert job.
     *
     * @param int[] $eventIds
     * @param string $now
     * @return void
     */
    public function markNotified(array $eventIds, string $now): void
    {
        if ($eventIds) {
            $this->getConnection()->update(
                $this->getMainTable(),
                ['notified_at' => $now],
                ['event_id IN (?)' => $eventIds]
            );
        }
    }

    /**
     * Latest events of a script, newest first.
     *
     * @param int $scriptId
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getForScript(int $scriptId, int $limit = 50): array
    {
        $connection = $this->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable())
                ->where('script_id = ?', $scriptId)
                ->order('event_id DESC')
                ->limit($limit)
        );
    }
}
