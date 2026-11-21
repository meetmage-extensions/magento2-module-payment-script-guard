<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Report;

use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Script inventory export for your PCI DSS assessor (requirement 6.4.3 evidence).
 */
class CsvReport
{
    private const COLUMNS = [
        'ID', 'Type', 'Script', 'Host', 'First-party', 'Status', 'Business justification', 'Authorized by',
        'Authorized at (UTC)', 'Hosts referenced (inline)', 'Pages', 'First seen (UTC)', 'Last seen (UTC)',
        'Times seen', 'Integrity status', 'Approved SHA-384', 'Current SHA-384', 'Last integrity check (UTC)',
    ];

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
     * CSV content of the whole inventory.
     *
     * @return string
     */
    public function build(): string
    {
        $connection = $this->scriptResource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->scriptResource->getMainTable())
                ->order(['status ASC', 'type ASC', 'label ASC'])
        );

        $lines = [
            $this->line([
                'Payment Script Guard: payment page script inventory',
                'Generated ' . $this->dateTime->gmtDate() . ' UTC',
            ]),
            $this->line(self::COLUMNS),
        ];
        foreach ($rows as $row) {
            $inline = $row['type'] === Script::TYPE_INLINE;
            $lines[] = $this->line([
                $row['script_id'],
                $inline ? 'Inline' : 'File',
                $inline ? $row['label'] : ($row['url'] ?: $row['label']),
                $row['host'],
                $row['first_party'] ? 'Yes' : 'No',
                ucfirst((string) $row['status']),
                $row['justification'],
                $row['authorized_by'],
                $row['authorized_at'],
                str_replace("\n", ', ', (string) $row['referenced_hosts']),
                $row['pages'],
                $row['first_seen_at'],
                $row['last_seen_at'],
                $row['seen_count'],
                $row['integrity_status'],
                $row['baseline_hash'],
                $row['current_hash'],
                $row['last_checked_at'],
            ]);
        }
        // UTF-8 byte order mark, so spreadsheet programs show non-ASCII characters correctly
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * One CSV line (RFC 4180 quoting).
     *
     * @param array<int,mixed> $values
     * @return string
     */
    private function line(array $values): string
    {
        return implode(',', array_map(function ($value): string {
            return '"' . str_replace('"', '""', $this->safe((string) $value)) . '"';
        }, $values));
    }

    /**
     * Value safe to open in a spreadsheet (no formula injection from script URLs or code).
     *
     * @param string $value
     * @return string
     */
    private function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
