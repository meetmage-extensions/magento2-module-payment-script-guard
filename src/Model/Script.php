<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * One script seen on a payment page (inventory entry).
 */
class Script extends AbstractModel
{
    public const TYPE_EXTERNAL = 'external';
    public const TYPE_INLINE = 'inline';
    public const TYPE_ATTRIBUTE = 'attribute';

    public const STATUS_NEW = 'new';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_REJECTED = 'rejected';

    public const INTEGRITY_PENDING = 'pending';
    public const INTEGRITY_OK = 'ok';
    public const INTEGRITY_CHANGED = 'changed';
    public const INTEGRITY_UNREACHABLE = 'unreachable';
    public const INTEGRITY_NOT_APPLICABLE = 'n/a';

    /**
     * @var string
     */
    protected $_eventPrefix = 'meetmage_psg_script';

    /**
     * Initialize the resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Script::class);
    }

    /**
     * Whether this is an external script file.
     *
     * @return bool
     */
    public function isExternal(): bool
    {
        return $this->getData('type') === self::TYPE_EXTERNAL;
    }
}
