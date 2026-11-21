<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MeetMage\PaymentScriptGuard\Model\Script as ScriptModel;

/**
 * Resource model for the script inventory.
 */
class Script extends AbstractDb
{
    public const TABLE = 'meetmage_psg_script';
    public const CACHE_TABLE = 'meetmage_psg_inline_cache';

    /**
     * Initialize the main table.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'script_id');
    }

    /**
     * Script ID for an inventory key, or null.
     *
     * @param string $scriptKey
     * @return int|null
     */
    public function getIdByKey(string $scriptKey): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'script_id')->where('script_key = ?', $scriptKey)
        );
        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Inline source hashes (exact SHA-256) that are already known, mapped to their script ID.
     *
     * @param string[] $rawHashes
     * @return array<string,int>
     */
    public function getKnownInlineHashes(array $rawHashes): array
    {
        if (!$rawHashes) {
            return [];
        }
        $connection = $this->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->getTable(self::CACHE_TABLE), ['raw_hash', 'script_id'])
                ->where('raw_hash IN (?)', $rawHashes)
        );
        return array_map('intval', $rows);
    }

    /**
     * Remember that an exact inline source belongs to a script.
     *
     * @param string $rawHash
     * @param int $scriptId
     * @param string $now
     * @return void
     */
    public function rememberInlineHash(string $rawHash, int $scriptId, string $now): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getTable(self::CACHE_TABLE),
            ['raw_hash' => $rawHash, 'script_id' => $scriptId, 'last_seen_at' => $now],
            ['script_id', 'last_seen_at']
        );
    }

    /**
     * Record that scripts were seen again: bump counters and add the page.
     *
     * @param int[] $scriptIds
     * @param string $page
     * @param string $now
     * @return void
     */
    public function touch(array $scriptIds, string $page, string $now): void
    {
        if (!$scriptIds) {
            return;
        }
        $connection = $this->getConnection();
        $quotedPage = $connection->quote($page);
        $connection->update(
            $this->getMainTable(),
            [
                'last_seen_at' => $now,
                'seen_count' => new \Zend_Db_Expr('seen_count + 1'),
                'pages' => new \Zend_Db_Expr(sprintf(
                    "IF(pages IS NULL OR pages = '', %1\$s,"
                    . " IF(FIND_IN_SET(%1\$s, pages) > 0 OR CHAR_LENGTH(pages) > 200,"
                    . " pages, CONCAT(pages, ',', %1\$s)))",
                    $quotedPage
                )),
            ],
            ['script_id IN (?)' => $scriptIds]
        );
        $connection->update(
            $this->getTable(self::CACHE_TABLE),
            ['last_seen_at' => $now],
            ['script_id IN (?)' => $scriptIds]
        );
    }

    /**
     * Number of scripts first seen since the given time (abuse limit for the public endpoint).
     *
     * @param string $since
     * @return int
     */
    public function countCreatedSince(string $since): int
    {
        $connection = $this->getConnection();
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), new \Zend_Db_Expr('COUNT(*)'))
                ->where('first_seen_at >= ?', $since)
        );
    }

    /**
     * Number of scripts that need attention (new, or changed since they were authorized).
     *
     * @return int
     */
    public function countNeedingReview(): int
    {
        $connection = $this->getConnection();
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), new \Zend_Db_Expr('COUNT(*)'))
                ->where('status = ?', ScriptModel::STATUS_NEW)
                ->orWhere('integrity_status = ?', ScriptModel::INTEGRITY_CHANGED)
        );
    }

    /**
     * Delete cached inline hashes not seen since the given time.
     *
     * @param string $before
     * @return int
     */
    public function pruneInlineCache(string $before): int
    {
        return $this->getConnection()->delete(
            $this->getTable(self::CACHE_TABLE),
            ['last_seen_at < ?' => $before]
        );
    }
}
