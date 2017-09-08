<?php

namespace Mash2\Cobby\Model\Data;


class ImportProductsFinish extends \Magento\Framework\Api\AbstractSimpleObject
    implements \Mash2\Cobby\Api\Data\ImportProductsFinishInterface
{
    /**
     * @return \Mash2\Cobby\Api\Data\ImportProductsFinishEntityInterface[]
     */
    public function getEntities()
    {
        return $this->_get(self::ENTITIES);
    }

    /**
     * @param \Mash2\Cobby\Api\Data\ImportProductsFinishEntityInterface[] $entities
     * @return $this
     */
    public function setEntities(array $entities)
    {
        return $this->setData(self::ENTITIES, $entities);
    }
}