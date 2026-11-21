<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\ResourceModel\Script;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Script inventory collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'script_id';

    /**
     * Initialize model and resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Script::class, ScriptResource::class);
    }
}
