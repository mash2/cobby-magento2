<?php
namespace Mash2\Cobby\Model;

class AttributeManagement implements \Mash2\Cobby\Api\AttributeManagementInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    protected $attributeCollection;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var\Magento\Catalog\Model\ResourceModel\Product
     */
    protected $productResource;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
     */
    protected $optionCollectionFactory;

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Store\Model\ResourceModel\Store\Collection $storeCollection
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $optionCollection
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory
    ) {
//    eav/entity_attribute_option_collection
        $this->attributeCollection = $attributeCollection;
        $this->storeManager = $storeManager;
        $this->productResource = $productResource;
        $this->optionCollectionFactory = $optionCollectionFactory;
    }

    public function getList($attributeSetId)
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

    public function getOptions($attributeId)
    {
        $result = array();

        foreach ($this->storeManager->getStores(true) as $store) {
            $storeId = $store->getStoreId();

            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            $attribute = $this->productResource
                ->getAttribute($attributeId)
                ->setStoreId($storeId);

            //some magento extension use boolean as input type, but forgot to set source model too boolean
            //magento renders the fields properly because of dropdown fields
            //we are setting the source_model to boolean to get the localized values for yes/no fields
            if ( $attribute->getFrontendInput() === 'boolean'  &&
                ($attribute->getData('source_model') == '' || $attribute->getData('source_model') == 'eav/entity_attribute_source_table') ) {
                $attribute->setSourceModel('Magento\Eav\Model\Entity\Attribute\Source\Boolean');
            }
            
            if ($attribute->usesSource()) {
                if( $attribute->getSource() instanceof \Magento\Eav\Model\Entity\Attribute\Source\Table  ) {
                    $options = $this->optionCollectionFactory->create()
                        ->setPositionOrder('asc')
                        ->setAttributeFilter($attributeId)
                        ->setStoreFilter($storeId);

                    foreach($options as $option) {
                        $result[] = array(
                            'store_id' => $storeId,
                            'value' => $option->getId(),
                            'label' => $option->getValue(),
                            'use_default' => $storeId > \Magento\Store\Model\Store::DEFAULT_STORE_ID && $option->getStoreDefaultValue() == null
                        ) ;
                    }
                } else {
                    foreach ($attribute->getSource()->getAllOptions(false, true) as $optionValue) {
                        $result[] = array(
                            'store_id' => $storeId,
                            'value' => $optionValue['value'],
                            'label' => $optionValue['label'],
                            'use_default' => false
                        );
                    }
                }
            }
        }

        return $result;
    }
}
