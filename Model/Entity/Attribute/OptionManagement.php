<?php

/*
 * @copyright Copyright (c) 2021 cobby GmbH & Co. KG. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 */

namespace Mash2\Cobby\Model\Entity\Attribute;

use Magento\Eav\Api\Data\AttributeInterface as EavAttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;

/**
 * Eav Option Management
 */
class OptionManagement extends \Magento\Eav\Model\Entity\Attribute\OptionManagement
{
    
    /**
     * @param AttributeRepository $attributeRepository
     * @param Attribute $resourceModel
     * @codeCoverageIgnore
     */
    public function __construct(
        AttributeRepository $attributeRepository,
        Attribute $resourceModel
    )
    {
        parent::__construct($attributeRepository, $resourceModel );
    }

    /**
     * Add option to attribute.
     *
     * @param int $entityType
     * @param string $attributeCode
     * @param AttributeOptionInterface $option
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function add($entityType, $attributeCode, $option)
    {
        // added this because this bug exists in Magento https://github.com/magento/magento2/issues/32608 
        $attribute = $this->loadAttribute($entityType, (string)$attributeCode);
        
        $label = trim(is_string($option->getLabel()) ? $option->getLabel() : '');
        if (!isset($label) && strlen($label) == 0) {
            throw new InputException(__('The attribute option label is empty. Enter the value and try again.'));
        }
    
        if ($attribute->getSource()->getOptionId($label) !== null) {
            throw new InputException(
                __(
                    'Admin store attribute option label "%1" is already exists.',
                    $option->getLabel()
                )
            );
        }
    
        $optionId = $this->getNewOptionId($option);
        $this->saveOption($attribute, $option, $optionId);
    
        return $this->retrieveOptionId($attribute, $option);
    }

    /**
     * Save attribute option
     *
     * @param EavAttributeInterface $attribute
     * @param AttributeOptionInterface $option
     * @param int|string $optionId
     * @return AttributeOptionInterface
     * @throws StateException
     */
    private function saveOption(
        EavAttributeInterface $attribute,
        AttributeOptionInterface $option,
        $optionId
    ): AttributeOptionInterface {
        $optionLabel = trim($option->getLabel());
        $options = [];
        $options['value'][$optionId][0] = $optionLabel;
        $options['order'][$optionId] = $option->getSortOrder();
        if (is_array($option->getStoreLabels())) {
            foreach ($option->getStoreLabels() as $label) {
                $options['value'][$optionId][$label->getStoreId()] = $label->getLabel();
            }
        }
        if ($option->getIsDefault()) {
            $attribute->setDefault([$optionId]);
        }

        $attribute->setOption($options);
        try {
            $this->resourceModel->save($attribute);
        } catch (\Exception $e) {
            throw new StateException(__('The "%1" attribute can\'t be saved.', $attribute->getAttributeCode()));
        }

        return $option;
    }

    /**
     * Get option id to create new option
     *
     * @param AttributeOptionInterface $option
     * @return string
     */
    private function getNewOptionId(AttributeOptionInterface $option): string
    {
        $optionId = trim($option->getValue() ?: '');
        if (empty($optionId)) {
            $optionId = 'new_option';
        }

        return 'id_' . $optionId;
    }
    
    /**
     * Load attribute
     *
     * @param string|int $entityType
     * @param string $attributeCode
     * @return EavAttributeInterface
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    private function loadAttribute($entityType, string $attributeCode): EavAttributeInterface
    {
        if (empty($attributeCode)) {
            throw new InputException(__('The attribute code is empty. Enter the code and try again.'));
        }

        $attribute = $this->attributeRepository->get($entityType, $attributeCode);
        if (!$attribute->usesSource()) {
            throw new StateException(__('The "%1" attribute doesn\'t work with options.', $attributeCode));
        }

        $attribute->setStoreId(0);

        return $attribute;
    }

    /**
     * Retrieve option id
     *
     * @param EavAttributeInterface $attribute
     * @param AttributeOptionInterface $option
     * @return string
     */
    private function retrieveOptionId(
        EavAttributeInterface $attribute,
        AttributeOptionInterface $option
    ) : string {
        $label = trim($option->getLabel());
        $optionId = $attribute->getSource()->getOptionId($label);
        if ($optionId) {
            $option->setValue($optionId);
        } elseif (is_array($option->getStoreLabels())) {
            foreach ($option->getStoreLabels() as $label) {
                $optionId = $attribute->getSource()->getOptionId($label->getLabel());
                if ($optionId) {
                    break;
                }
            }
        }

        return (string) $optionId;
    }
}
