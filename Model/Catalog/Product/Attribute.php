<?php
namespace Mash2\Cobby\Model\Catalog\Product;

/**
 * Class Attribute
 * @package Mash2\Cobby\Model\Catalog\Product
 */
class Attribute implements \Mash2\Cobby\Api\CatalogProductAttributeInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    protected $attributeCollection;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    protected $productResource;

    /**
     * Api constructor.
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection,
        \Magento\Catalog\Model\ResourceModel\Product $productResource
    ){
        $this->attributeCollection = $attributeCollection;
        $this->productResource = $productResource;
    }

    /**
     * {@inheritdoc}
     */
    public function export($attributeSetId = null, $attributeId = null)
    {
        $result = array();

        if ($attributeId){
            $attribute = $this->productResource->getAttribute($attributeId);

            $result[] = $this->getAttribute($attribute);
        }

        if ($attributeSetId){
            $attributes = $this->attributeCollection
                ->setAttributeSetFilter($attributeSetId)
                ->load();



            foreach ($attributes as $attribute) {
                $result[] = $this->getAttribute($attribute);
            }
        }

        return $result;
    }

    public function getAttribute($attribute){
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

        $result = array_merge(
            $attribute->getData(),
            array(
                'scope' => $attribute->getScope(),
                'apply_to' => $attribute->getApplyTo(),
                'store_labels' => $storeLabels
            ));

        return $result;
    }
}