<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MeetMage\PaymentScriptGuard\Model\Event;

/**
 * Event severity options.
 */
class Severity implements OptionSourceInterface
{
    /**
     * Options for grids.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Event::SEVERITY_CRITICAL, 'label' => __('Critical')],
            ['value' => Event::SEVERITY_WARNING, 'label' => __('Warning')],
            ['value' => Event::SEVERITY_INFO, 'label' => __('Info')],
        ];
    }
}
