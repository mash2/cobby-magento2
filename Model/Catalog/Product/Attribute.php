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
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * Api constructor.
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ){
        $this->attributeCollection = $attributeCollection;
        $this->productResource = $productResource;
        $this->eventManager = $eventManager;
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
                $data = $this->getAttribute($attribute);

                $transportObject = new \Magento\Framework\DataObject();
                $transportObject->setData($data);

                $this->eventManager->dispatch('cobby_catalog_attribute_export_after', array(
                    'attribute' => $attribute, 'transport' => $transportObject));

                $result[] = $transportObject->getData();
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