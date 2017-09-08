<?php
namespace Mash2\Cobby\Model\ResourceModel\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Mash2\Cobby\Model\Queue', 'Mash2\Cobby\Model\ResourceModel\Queue');
    }

    /**
     * @param int $minQueueId
     * @return Collection
     */
    public function addMinQueueIdFilter($minQueueId)
    {
        $this->addFieldToFilter($this->getResource()->getIdFieldName(), array('gteq' => $minQueueId));

        return $this;
    }

    /**
     * Deletes all table rows
     *
     * @return int The number of affected rows.
     */
    public function delete()
    {
        return $this->getConnection()->delete($this->getMainTable());
    }
}