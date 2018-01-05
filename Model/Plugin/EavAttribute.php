<?php
namespace Mash2\Cobby\Model\Plugin;

/**
 * Plugin model for Catalog Resource Attribute
 */
class EavAttribute extends \Magento\Swatches\Model\Plugin\EavAttribute
{
    /**
     * Substitute suitable options and swatches arrays
     *
     * @param Attribute $attribute
     * @return void
     */
    protected function setProperOptionsArray(\Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute)
    {
        $canReplace = false;
        if ($this->swatchHelper->isVisualSwatch($attribute)) {
            $canReplace = true;
            $defaultValue = $attribute->getData('defaultvisual');
            $optionsArray = $attribute->getData('optionvisual');
            $swatchesArray = $attribute->getData('swatchvisual');
        } elseif ($this->swatchHelper->isTextSwatch($attribute)) {
            $canReplace = true;
            $defaultValue = $attribute->getData('defaulttext');
            $optionsArray = $attribute->getData('optiontext');
            $swatchesArray = $attribute->getData('swatchtext');
        }
        if ($canReplace == true) {
            if (!empty($optionsArray)) {
                $attribute->setData('option', $optionsArray);
            }
            if (!empty($defaultValue)) {
                $attribute->setData('default', $defaultValue);
            } else {
                $attribute->setData('default', [0 => $attribute->getDefaultValue()]);
            }
            if (!empty($swatchesArray)) {
                $attribute->setData('swatch', $swatchesArray);
            }
        }
    }
}
