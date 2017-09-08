<?php
namespace Mash2\Cobby\Model\Plugin\Store;


class Store extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{
    public function aroundSave(
        \Magento\Store\Model\ResourceModel\Store $storeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $store
    ) {
        $storeResource->addCommitCallback(function () use ($store) {
            $this->resetHash('store_changed');
        });

        return $proceed($store);
    }

    public function aroundDelete(
        \Magento\Store\Model\ResourceModel\Store $storeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $store
    ){
        $this->resetHash('store_changed');

        return $proceed($store);
    }
}