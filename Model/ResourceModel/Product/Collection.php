<?php
namespace Mash2\Cobby\Model\ResourceModel\Product;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('Mash2\Cobby\Model\Product', 'Mash2\Cobby\Model\ResourceModel\Product');
    }

}