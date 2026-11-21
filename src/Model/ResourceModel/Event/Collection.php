<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\ResourceModel\Event;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MeetMage\PaymentScriptGuard\Model\Event;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Event as EventResource;

/**
 * Activity log collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'event_id';

    /**
     * Initialize model and resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Event::class, EventResource::class);
    }
}
