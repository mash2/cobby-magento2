<?php
/**
 * AddSwatchDataToAddOption
 *
 * @copyright Copyright © 2017 Bitbull. All rights reserved.
 * @author    andra.lungu@bitbull.it
 */

namespace Mash2\Cobby\Model\Plugin\Catalog;

use Magento\Eav\Model\Entity\Attribute\OptionManagement;
use Magento\Eav\Model\Config;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollection;


class AddSwatchDataToAddOption
{
    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     * @var SwatchHelper
     */
    protected $swatchHelper;

    /**
     * @var OptionCollection
     */
    protected $attrOptionCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute
     */
    protected $attrFactory;

    /**
     * @param Config $eavConfig
     * @param SwatchHelper $swatchHelper
     * @param OptionCollection $attrOptionCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory
     */
    public function __construct(
        Config $eavConfig,
        SwatchHelper $swatchHelper,
        OptionCollection $attrOptionCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory
    ) {
        $this->eavConfig                    = $eavConfig;
        $this->swatchHelper                 = $swatchHelper;
        $this->attrOptionCollectionFactory  = $attrOptionCollectionFactory;
        $this->attrFactory                  = $attributeFactory->create();
    }

    /**
     * @param OptionManagement $subject
     * @param int $entityType
     * @param string $attributeCode
     * @param AttributeOptionInterface $option
     *
     * @return null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeAdd(OptionManagement $subject, $entityType, $attributeCode, $option)
    {
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);

        $isSwatch = false;
        if ($this->swatchHelper->isVisualSwatch($attribute)) {
            $isSwatch  = true;
            $optionKey = 'optionvisual';
            $swatchKey = 'swatchvisual';
        } elseif ($this->swatchHelper->isTextSwatch($attribute)) {
            $isSwatch  = true;
            $optionKey = 'optiontext';
            $swatchKey = 'swatchtext';
        }

        if ($isSwatch && $attribute->getData($optionKey) === null && $attribute->getData($swatchKey) === null) {
            $optionId    = $option->getValue();
            $optionOrder = $option->getSortOrder();

            $prefix = '';
            if ($optionId === '') {
                $prefix        = 'option_';
                $options       = $this->getOptionsByAttributeIdWithSortOrder($attribute->getAttributeId());
                $optionArray   = $this->getOptionsForSwatch($options, $optionKey);
                $attributeData = $optionArray;
                $optionId      = count($optionArray[$optionKey]['value']);
                if ($optionOrder === null) {
                    $optionOrder = $optionId + 1;
                }
                $option->setValue($prefix . $optionId);
            }


            $storeLabels                                              = $option->getStoreLabels();
            $attributeData[$optionKey]['delete'][$prefix . $optionId] = '';
            $attributeData[$optionKey]['order'][$prefix . $optionId]  = $optionOrder;
            if ($swatchKey === 'swatchvisual') {
                $attributeData[$swatchKey]['value'][$prefix . $optionId] = '';
            }
            foreach ($storeLabels as $storeLabel) {
                $attributeData[$optionKey]['value'][$prefix . $optionId][$storeLabel->getStoreId()]
                    = $storeLabel->getLabel();
                if ($swatchKey === 'swatchtext') {
                    $attributeData[$swatchKey]['value'][$prefix . $optionId][$storeLabel->getStoreId()]
                        = $storeLabel->getLabel();
                }
            }
            $attribute->addData($attributeData);

            $this->saveAttr($attributeData, $attribute);
        }

        return [$entityType, $attributeCode, $option];

    }

    protected function saveAttr($additionalData, $attribute)
    {
        $model = $this->attrFactory;
        $model->load($attribute->getAttributeId());

        $data['attribute_code'] = $model->getAttributeCode();
        $data['is_user_defined'] = $model->getIsUserDefined();
        $data['frontend_input'] = $model->getFrontendInput();

        $data += ['is_filterable' => 0, 'is_filterable_in_search' => 0];
        $model->addData($data);

        $model->addData($additionalData);
        $model->save();
    }

    /**
     * @param [] $options
     * @param string $optionKey
     *
     * @return array
     */
    protected function getOptionsForSwatch($options, $optionKey)
    {
        $optionsArray = [];

        if ( count($options) === 0) {
            $optionsArray[$optionKey]['value']  = [];
            $optionsArray[$optionKey]['delete'] = [];
            return $optionsArray;
        }
        foreach ($options as $sortOrder => $optionId) {
            $optionsArray[$optionKey]['value'][$optionId]  = $this->getStoreLabels($optionId);
            $optionsArray[$optionKey]['delete'][$optionId] = '';
            $optionsArray[$optionKey]['order'][$optionId] =  (string)$sortOrder;
        }

        return $optionsArray;
    }


    /**
     * @param $optionId
     *
     * @return array
     */
    protected function getStoreLabels($optionId)
    {
        $attrOptionCollectionFactory = $this->attrOptionCollectionFactory->create();
        $connection                  = $attrOptionCollectionFactory->getConnection();
        $eavAttributeOptionValue     = $attrOptionCollectionFactory->getTable('eav_attribute_option_value');
        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select()->from(['eaov' => $eavAttributeOptionValue], [])
                                       ->where('option_id = ?', $optionId)
                                       ->columns(['store_id', 'value']);

        return $connection->fetchPairs($select);
    }

    /**
     * @param $attributeId
     *
     * @return array
     */
    protected function getOptionsByAttributeIdWithSortOrder($attributeId)
    {
        $attrOptionCollectionFactory = $this->attrOptionCollectionFactory->create();
        $connection                  = $attrOptionCollectionFactory->getConnection();
        $eavAttributeOption          = $attrOptionCollectionFactory->getTable('eav_attribute_option');
        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select()
                             ->from(['eao' => $eavAttributeOption], ['option_id', 'sort_order'])
                             ->where('eao.attribute_Id = ? ', $attributeId)
                             ->order('eao.sort_order ASC');

        return $connection->fetchCol($select);
    }
}