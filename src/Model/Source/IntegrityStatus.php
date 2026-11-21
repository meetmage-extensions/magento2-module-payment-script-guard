<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Integrity check status options.
 */
class IntegrityStatus implements OptionSourceInterface
{
    /**
     * Options for grids.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Script::INTEGRITY_PENDING, 'label' => __('Not checked yet')],
            ['value' => Script::INTEGRITY_OK, 'label' => __('Unchanged')],
            ['value' => Script::INTEGRITY_CHANGED, 'label' => __('Changed')],
            ['value' => Script::INTEGRITY_UNREACHABLE, 'label' => __('Could not fetch')],
            ['value' => Script::INTEGRITY_NOT_APPLICABLE, 'label' => __('By fingerprint')],
        ];
    }
}
