<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Authorization status options.
 */
class Status implements OptionSourceInterface
{
    /**
     * Options for grids and forms.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Script::STATUS_NEW, 'label' => __('Needs review')],
            ['value' => Script::STATUS_AUTHORIZED, 'label' => __('Authorized')],
            ['value' => Script::STATUS_REJECTED, 'label' => __('Rejected')],
        ];
    }
}
