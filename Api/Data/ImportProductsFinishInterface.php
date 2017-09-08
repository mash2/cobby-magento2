<?php

namespace Mash2\Cobby\Api\Data;

/**
 * Interface ImportProductsFinishInterface
 * @api
 */
interface ImportProductsFinishInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    /**#@+
     * Constants defined for parameters event_type
     * and entities
     */
    const ENTITIES = 'entities';

    /**
     * @return \Mash2\Cobby\Api\Data\ImportProductsFinishEntityInterface[]
     */
    public function getEntities();

    /**
     * @param \Mash2\Cobby\Api\Data\ImportProductsFinishEntityInterface[] $entities
     * @return $this
     */
    public function setEntities(array $entities);
}



