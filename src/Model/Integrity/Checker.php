<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Integrity;

use Magento\Framework\App\View\Deployment\Version as DeploymentVersion;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MeetMage\PaymentScriptGuard\Model\Event;
use MeetMage\PaymentScriptGuard\Model\EventLogger;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Compares external scripts with their approved SHA-384 hash (PCI DSS 4.0.1 requirement 11.6.1).
 *
 * - First-party files: when the static content version changed since the last check, a new hash is
 *   accepted as the result of the deployment; a change without a new deployment is reported as
 *   possible tampering (critical).
 * - Third-party files: only authorized ones are fetched. A change is reported unless the script is
 *   marked as "expected to change" (versionless vendor URLs).
 */
class Checker
{
    private const BATCH_LIMIT = 1000;

    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var Fetcher
     */
    private Fetcher $fetcher;

    /**
     * @var EventLogger
     */
    private EventLogger $eventLogger;

    /**
     * @var DeploymentVersion
     */
    private DeploymentVersion $deploymentVersion;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ScriptResource $scriptResource
     * @param Fetcher $fetcher
     * @param EventLogger $eventLogger
     * @param DeploymentVersion $deploymentVersion
     * @param DateTime $dateTime
     */
    public function __construct(
        ScriptResource $scriptResource,
        Fetcher $fetcher,
        EventLogger $eventLogger,
        DeploymentVersion $deploymentVersion,
        DateTime $dateTime
    ) {
        $this->scriptResource = $scriptResource;
        $this->fetcher = $fetcher;
        $this->eventLogger = $eventLogger;
        $this->deploymentVersion = $deploymentVersion;
        $this->dateTime = $dateTime;
    }

    /**
     * SRI-format SHA-384 of the content (sha384-<base64>).
     *
     * @param string $content
     * @return string
     */
    public function sri(string $content): string
    {
        return 'sha384-' . base64_encode(hash('sha384', $content, true));
    }

    /**
     * Check every external script that can be checked.
     *
     * @return array{checked:int,changed:int,unreachable:int}
     */
    public function checkAll(): array
    {
        $connection = $this->scriptResource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->scriptResource->getMainTable())
                ->where('type = ?', Script::TYPE_EXTERNAL)
                ->where('status != ?', Script::STATUS_REJECTED)
                ->where('integrity_status != ?', Script::INTEGRITY_NOT_APPLICABLE)
                ->where('first_party = 1 OR status = ?', Script::STATUS_AUTHORIZED)
                ->order('last_checked_at ASC')
                ->limit(self::BATCH_LIMIT)
        );
        $stats = ['checked' => 0, 'changed' => 0, 'unreachable' => 0];
        $version = $this->currentDeployVersion();
        foreach ($rows as $row) {
            $status = $this->check($row, $version);
            $stats['checked']++;
            if ($status === Script::INTEGRITY_CHANGED) {
                $stats['changed']++;
            } elseif ($status === Script::INTEGRITY_UNREACHABLE) {
                $stats['unreachable']++;
            }
        }
        return $stats;
    }

    /**
     * Fetch a script now and make its current content the approved baseline (used on authorization).
     *
     * @param int $scriptId
     * @return string Integrity status after the check
     */
    public function approveCurrent(int $scriptId): string
    {
        $row = $this->loadRow($scriptId);
        if (!$row || $row['type'] !== Script::TYPE_EXTERNAL
            || $row['integrity_status'] === Script::INTEGRITY_NOT_APPLICABLE
        ) {
            return Script::INTEGRITY_NOT_APPLICABLE;
        }
        $content = $this->fetcher->fetch((string) $row['url'], (bool) $row['first_party']);
        if ($content === null) {
            $this->update($scriptId, [
                'baseline_hash' => null,
                'integrity_status' => Script::INTEGRITY_UNREACHABLE,
                'last_checked_at' => $this->dateTime->gmtDate(),
            ]);
            return Script::INTEGRITY_UNREACHABLE;
        }
        $hash = $this->sri($content);
        $this->update($scriptId, [
            'baseline_hash' => $hash,
            'current_hash' => $hash,
            'integrity_status' => Script::INTEGRITY_OK,
            'deploy_version' => $this->currentDeployVersion(),
            'last_checked_at' => $this->dateTime->gmtDate(),
        ]);
        return Script::INTEGRITY_OK;
    }

    /**
     * Check one script now (the "Check now" button).
     *
     * @param int $scriptId
     * @return string
     */
    public function checkOne(int $scriptId): string
    {
        $row = $this->loadRow($scriptId);
        return $row ? $this->check($row, $this->currentDeployVersion()) : Script::INTEGRITY_NOT_APPLICABLE;
    }

    /**
     * Check one inventory row and store the result.
     *
     * @param array<string,mixed> $row
     * @param string $deployVersion
     * @return string
     */
    private function check(array $row, string $deployVersion): string
    {
        $id = (int) $row['script_id'];
        $firstParty = (bool) $row['first_party'];
        $now = $this->dateTime->gmtDate();
        $content = $this->fetcher->fetch((string) $row['url'], $firstParty);

        if ($content === null) {
            $this->update($id, ['integrity_status' => Script::INTEGRITY_UNREACHABLE, 'last_checked_at' => $now]);
            return Script::INTEGRITY_UNREACHABLE;
        }

        $hash = $this->sri($content);
        $data = ['current_hash' => $hash, 'last_checked_at' => $now, 'deploy_version' => $deployVersion];
        $baseline = (string) $row['baseline_hash'];

        if ($baseline === '' || $hash === $baseline) {
            $data['baseline_hash'] = $hash;
            $data['integrity_status'] = Script::INTEGRITY_OK;
            $this->update($id, $data);
            return Script::INTEGRITY_OK;
        }

        if ($firstParty && (string) $row['deploy_version'] !== $deployVersion) {
            $data['baseline_hash'] = $hash;
            $data['integrity_status'] = Script::INTEGRITY_OK;
            $this->update($id, $data);
            $this->eventLogger->log(
                Event::TYPE_REBASELINED,
                Event::SEVERITY_INFO,
                sprintf('%s changed with deployment %s; the new version was accepted.', $row['label'], $deployVersion),
                $id
            );
            return Script::INTEGRITY_OK;
        }

        if (!$firstParty && (int) $row['allow_changes'] === 1) {
            $data['baseline_hash'] = $hash;
            $data['integrity_status'] = Script::INTEGRITY_OK;
            $this->update($id, $data);
            $this->eventLogger->log(
                Event::TYPE_HASH_CHANGED,
                Event::SEVERITY_INFO,
                sprintf('%s changed (allowed for this script). New hash: %s', $row['label'], $hash),
                $id
            );
            return Script::INTEGRITY_OK;
        }

        $data['integrity_status'] = Script::INTEGRITY_CHANGED;
        $this->update($id, $data);
        if ($row['integrity_status'] !== Script::INTEGRITY_CHANGED || (string) $row['current_hash'] !== $hash) {
            if ($firstParty) {
                $this->eventLogger->log(
                    Event::TYPE_TAMPERED,
                    Event::SEVERITY_CRITICAL,
                    sprintf(
                        '%s changed on the server without a new deployment. This is how card skimmers are '
                        . 'usually injected: check the file now. Expected %s, found %s.',
                        $row['label'],
                        $baseline,
                        $hash
                    ),
                    $id
                );
            } else {
                $this->eventLogger->log(
                    Event::TYPE_HASH_CHANGED,
                    Event::SEVERITY_WARNING,
                    sprintf('%s changed since it was authorized. Review the new version and approve it, or reject '
                        . 'the script. Expected %s, found %s.', $row['label'], $baseline, $hash),
                    $id
                );
            }
        }
        return Script::INTEGRITY_CHANGED;
    }

    /**
     * Current static content version (changes with every setup:static-content:deploy).
     *
     * @return string
     */
    private function currentDeployVersion(): string
    {
        try {
            return (string) $this->deploymentVersion->getValue();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Inventory row as an array.
     *
     * @param int $scriptId
     * @return array<string,mixed>|null
     */
    private function loadRow(int $scriptId): ?array
    {
        $connection = $this->scriptResource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->scriptResource->getMainTable())->where('script_id = ?', $scriptId)
        );
        return $row ?: null;
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
