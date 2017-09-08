<?php
namespace Mash2\Cobby\Model\Plugin\CatalogInventory;

/**
 * Class Stock
 * @package Mash2\Cobby\Model\Plugin\CatalogInventory
 */
class Stock extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{

    public function aroundSave(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $stock

    ) {
        $stockData = $stock->getQuote();

        $this->enqueueAndNotify('stock', 'save', $stock->getProductId());
        $this->updateHash($stock->getProductId());

        return $proceed($stock);
    }
}