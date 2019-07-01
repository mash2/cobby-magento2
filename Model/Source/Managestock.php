<?php
namespace Mash2\Cobby\Model\Source;

class Managestock implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_ENABLED, 'label' => __('enabled')],
            ['value' => \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_READONLY, 'label' => __('readonly')],
            ['value' => \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_DISABLED, 'label' => __('disabled')]
        ];
    }
}