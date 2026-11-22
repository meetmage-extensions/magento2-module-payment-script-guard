<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MeetMage\PaymentScriptGuard\Model\Script;

/**
 * Script type options.
 */
class ScriptType implements OptionSourceInterface
{
    /**
     * Options for grids.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Script::TYPE_EXTERNAL, 'label' => __('File')],
            ['value' => Script::TYPE_INLINE, 'label' => __('Inline')],
            ['value' => Script::TYPE_ATTRIBUTE, 'label' => __('Attribute code')],
        ];
    }
}
