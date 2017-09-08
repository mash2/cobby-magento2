<?php


namespace Mash2\Cobby\Model\Data;


class ImportProductsFinishEntity extends \Magento\Framework\Api\AbstractSimpleObject
    implements \Mash2\Cobby\Api\Data\ImportProductsFinishEntityInterface
{
    /**
     * @return string $sku
     */
    public function getSku()
    {
        return $this->_get(self::SKU);
    }

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @return int $productId
     */
    public function getProductId()
    {
        return $this->_get(self::PRODUCT_ID);
    }

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId($productId)
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }
}