<?php
namespace Mash2\Cobby\Model\Catalog\Product\Attribute;

/**
 * Class Option
 * @package Mash2\Cobby\Model\Catalog\Product\Attribute
 */
class Option implements \Mash2\Cobby\Api\CatalogProductAttributeOptionInterface
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
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * Import constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement
     * @param \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory
     * @param \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement,
        \Magento\Eav\Model\Entity\Attribute\OptionFactory $attrOptionFactory,
        \Magento\Eav\Model\Entity\Attribute\OptionLabelFactory $attrOptionLabelFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optionCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->productResource = $productResource;
        $this->registry = $registry;
        $this->productAttributeOptionManagement = $productAttributeOptionManagement;
        $this->attrOptionFactory = $attrOptionFactory;
        $this->attrOptionLabelFactory = $attrOptionLabelFactory;
        $this->storeManager = $storeManager;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->eventManager = $eventManager;
    }

    public function export($attributeId){
        $options = $this->getOptions($attributeId);

        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setData($options);

        $attribute = $this->productResource->getAttribute($attributeId);

        $this->eventManager->dispatch('cobby_catalog_product_attribute_option_export_after',
            array('attribute' => $attribute, 'transport' => $transportObject));

        return $transportObject->getData();
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

                        foreach($options as $option) {
                            if ($option->getValue() == $filter) {
                                $result[] = array(
                                    'store_id' => $storeId,
                                    'value' => $option->getId(),
                                    'label' => $option->getValue(),
                                    'use_default' => $storeId > \Magento\Store\Model\Store::DEFAULT_STORE_ID && $option->getStoreDefaultValue() == null
                                );
                            }
                        }
                    }
                    else{
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
                            );
                        }
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
        $rows = $this->jsonHelper->jsonDecode($jsonData);

        foreach ($rows as $row) {
            $attributeId = $row['attribute_id'];
            $attribute = $this->productResource->getAttribute($attributeId);

            if (!$attribute) {
                $result[] = array('attribute_id' => $attributeId,
                    'options' => null,
                    'error_code' => self::ERROR_NOT_EXISTS);
            }
            else {
                foreach ($row['options'] as $requestedOption) {
                    $label = $requestedOption['labels']['0']['value'];
                    $options = $this->getOptions($attributeId, $label);

                    if (empty($options) || (int)$requestedOption['option_id']) {
                        $this->_saveAttributeOptions($attribute, array($requestedOption));
                        $options = $this->getOptions($attributeId, $label);
                        $result[] = ['attribute_id' => $attributeId, 'options' => $options];
                    } else {
                        $result[] = ['attribute_id' => $attributeId, 'options' => $options,
                            'error_code' => self::ERROR_OPTION_ALREADY_EXISTS];
                    }
                }
            }
        }

        return $result;
    }

    private function _saveAttributeOptions($attribute, $data)
    {
        /* @var $option \Magento\Eav\Api\Data\AttributeOptionInterface */
        $option = $this->attrOptionFactory->create();
        $attributeCode = $attribute->getAttributeCode();

        foreach ($data as $row) {
            $optionLabels = array();
            $adminLabel = $row['labels']['0']['value'];

            foreach ($row['labels'] as $label) {
                /* @var $optionLabel \Magento\Eav\Api\Data\AttributeOptionLabelInterface */
                $optionLabel = $this->attrOptionLabelFactory->create();

                $optionLabel->setStoreId($label['store_id']);
                $optionLabel->setLabel($label['value']);
                $optionLabels[] = $optionLabel;
            }

            if(isset($row['option_id']) && (int)$row['option_id']) {
                $option->setValue($row['option_id']);
            }

            $option->setStoreLabels($optionLabels);
            $option->setLabel($adminLabel);
            $this->productAttributeOptionManagement->add($attributeCode, $option);
        }
    }
}