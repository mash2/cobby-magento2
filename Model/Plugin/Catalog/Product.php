<?php
namespace Mash2\Cobby\Model\Plugin\Catalog;

/**
 * Class Product
 * @package Mash2\Cobby\Model\Plugin\Catalog
 */
class Product extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{
    public function aroundSave(
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $product
    ) {
        $productResource->addCommitCallback(function () use ($product) {
            $this->enqueueAndNotify('product', 'save', $product->getId());
            $this->updateHash($product->getId());
        });
        return $proceed($product);
    }

    public function aroundDelete(
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $product
    ) {
        $this->enqueueAndNotify('product', 'delete', $product->getId());
        $this->updateHash($product->getId());

        return $proceed($product);
    }

    public function aroundUpdateAttributes(
        \Magento\Catalog\Model\Product\Action $subject,
        \Closure $closure,
        array $productIds,
        array $attrData,
        $storeId
    ) {
        $result = $closure($productIds, $attrData, $storeId);

        $this->enqueueAndNotify('product', 'save', array_unique($productIds));
        $this->updateHash($productIds);

        return $result;
    }

    public function aroundUpdateWebsites(
        \Magento\Catalog\Model\Product\Action $subject,
        \Closure $closure,
        array $productIds,
        array $websiteIds,
        $type
    ) {
        $result = $closure($productIds, $websiteIds, $type);

        $this->enqueueAndNotify('product', 'save', array_unique($productIds));
        $this->updateHash($productIds);

        return $result;
    }
}