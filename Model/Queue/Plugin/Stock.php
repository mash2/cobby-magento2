<?php
namespace Mash2\Cobby\Model\Queue\Plugin;

class Stock extends \Mash2\Cobby\Model\Queue\Plugin\AbstractPlugin
{
    public function aroundSave(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $stock
    ) {
        $this->enqueueAndNotify('stock', 'save', $stock->getProductId());
        return $proceed($stock);
    }
}