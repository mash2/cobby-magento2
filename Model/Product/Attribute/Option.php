<?php
namespace Mash2\Cobby\Model\Product\Attribute;

/**
 * Class Option
 * @package Mash2\Cobby\Model\Product\Attribute
 */
class Option implements \Mash2\Cobby\Api\ProductAttributeOptionInterface
{
    const ERROR_NOT_EXISTS = 'attribute_not_exists';
    const ERROR_OPTION_ALREADY_EXISTS = 'option_already_exists';

    /**
     * Json Helper
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

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
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeOptionManagementInterface
     */
    private $productAttributeOptionManagement;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\OptionFactory
     */
    private $attrOptionFactory;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\OptionLabel
     */
    private $attrOptionLabelFactory;

    /**
     * Import constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement
     * @param \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory
     * @param \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement,
        \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory,
        \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->productResource = $productResource;
        $this->registry = $registry;
        $this->productAttributeOptionManagement = $productAttributeOptionManagement;
        $this->attrOptionFactory = $attrOptionFactory;
        $this->attrOptionLabelFactory = $attrOptionLabelFactory;
        $this->storeManager = $storeManager;
        $this->optionCollectionFactory = $optionCollectionFactory;
    }

    public function export($attributeId){
        return $this->getOptions($attributeId);
    }

    public function getOptions($attributeId, $filter = null)
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
                    if ($filter != null){
                        $options = $this->optionCollectionFactory->create()
                            ->addFieldToFilter('tdv.value', $filter)
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);
                    }
                    else{
                        $options = $this->optionCollectionFactory->create()
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);
                    }


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

    public function import($jsonData)
    {
        $this->registry->register('is_cobby_import', 1);

        $result = array();
        $data = $this->jsonHelper->jsonDecode($jsonData);

        foreach ($data as $value) {
            $attributeId = $value['attribute_id'];
            $attribute = $this->productResource->getAttribute($attributeId);

            if (!$attribute) {
                $result[] = array('attribute_id' => $attributeId,
                    'options' => null,
                    'error_code' => self::ERROR_NOT_EXISTS);
            }
            else {
                $attributeCode = $attribute->getAttributeCode();
                $optionName = $value['options'][0]['labels'][0]['value'];
                $optionId = $value['options'][0]['option_id'];

                $options = $this->getOptions($attributeId, $optionName);

                if ($options == null || (int)$optionId) {

                    /* @var $option \Magento\Eav\Api\Data\AttributeOptionInterface */
                    $option = $this->attrOptionFactory->create();
                    $optionValues = $value['options'][0]['labels'];

                    if (count($optionValues) > 1) {
                        $stores = array();

                        foreach ($optionValues as $optionValue) {
                            /* @var $optionLabel \Magento\Eav\Api\Data\AttributeOptionLabelInterface */
                            $optionLabel = $this->attrOptionLabelFactory->create();

                            $optionLabel->setStoreId($optionValue['store_id']);
                            $optionLabel->setLabel($optionValue['value']);
                            $stores[] = $optionLabel;
                        }

                        $option->setStoreLabels($stores);
                    }

                    $option->setLabel($optionName);
                    $this->productAttributeOptionManagement->add($attributeCode, $option);

                    $options = $this->getOptions($attributeId, $optionName);

                    $result[] = ['attribute_id' => $attributeId, 'options'=>$options];
                }
                else {
                    $result[] = ['attribute_id' => $attributeId, 'options' => $options,
                        'error_code' => self::ERROR_OPTION_ALREADY_EXISTS];
                }
            }
        }

        return $result;
    }
}