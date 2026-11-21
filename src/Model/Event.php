<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Activity log entry; warnings and critical entries are also emailed.
 */
class Event extends AbstractModel
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public const TYPE_NEW_SCRIPT = 'new_script';
    public const TYPE_AUTO_AUTHORIZED = 'auto_authorized';
    public const TYPE_AUTHORIZED = 'authorized';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_REJECTED_SEEN = 'rejected_seen';
    public const TYPE_HASH_CHANGED = 'hash_changed';
    public const TYPE_TAMPERED = 'tampered';
    public const TYPE_REBASELINED = 'rebaselined';
    public const TYPE_HEADERS_CHANGED = 'headers_changed';
    public const TYPE_HEADERS_BASELINE = 'headers_baseline';
    public const TYPE_LIMIT_REACHED = 'limit_reached';

    /**
     * Initialize the resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Event::class);
    }
}
