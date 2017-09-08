<?php


namespace Mash2\Cobby\Api\Data;


interface ImportProductsFinishEntityInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    /**#@+
     * Constants defined for keys of entities array
     */
    const SKU = 'sku';
    const PRODUCT_ID = 'product_id';

    /**
     * @return string
     */
    public function getSku();

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku);

    /**
     * @return int
     */
    public function getProductId();

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId($productId);
}