<?php
namespace Mash2\Cobby\Model\Product;

/**
 * Class Attribute
 * @package Mash2\Cobby\Model\Product
 */
class Attribute implements \Mash2\Cobby\Api\ProductAttributeInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    protected $attributeCollection;

    /**
     * Api constructor.
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection
    ){
        $this->attributeCollection = $attributeCollection;
    }

    public function export($attributeSetId)
    {
        $result = array();

        $attributes = $this->attributeCollection
            ->setAttributeSetFilter($attributeSetId)
            ->load();

        foreach ($attributes as $attribute) {
            $storeLabels = array(
                array(
                    'store_id' => 0,
                    'label' => $attribute->getFrontendLabel()
                )
            );
            foreach ($attribute->getStoreLabels() as $store_id => $label) {
                $storeLabels[] = array(
                    'store_id' => $store_id,
                    'label' => $label
                );
            }

            $result[] = array_merge(
                $attribute->getData(),
                array(
                    'scope' => $attribute->getScope(),
                    'apply_to' => $attribute->getApplyTo(),
                    'store_labels' => $storeLabels
                ));
        }

        return $result;
    }
}