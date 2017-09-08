<?php
namespace Mash2\Cobby\Model\ResourceModel;


class Product extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function _construct()
    {
        $this->_init('mash2_cobby_product', 'entity_id');
    }

    public function resetHash($hash)
    {
        $this->getConnection()->update($this->getMainTable(), array('hash' => $hash));
        return $this;
    }

    public function updateHash($ids, $hash)
    {
        $select = $this->getConnection()
            ->select()
            ->from(array('cp' => $this->getTable('catalog_product_entity')), array('entity_id', new \Zend_Db_Expr('"'. $hash . '" as hash')))
            ->where('cp.entity_id IN (?)', $ids)
            ->insertFromSelect($this->getMainTable(), array('entity_id', 'hash'), \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_ON_DUPLICATE);

        $this->getConnection()->query($select);
        return $this;
    }
}