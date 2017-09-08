<?php
namespace Mash2\Cobby\Model\ResourceModel;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('mash2_cobby_queue', 'queue_id');
    }
}