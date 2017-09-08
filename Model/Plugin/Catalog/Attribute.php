<?php
namespace Mash2\Cobby\Model\Plugin\Catalog;

class Attribute extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{

    public function aroundSave(
        \Magento\Catalog\Model\ResourceModel\Attribute $attributeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $attribute
    ) {
        $attributeResource->addCommitCallback(function () use ($attribute) {
            $this->enqueueAndNotify('attribute', 'save', $attribute->getId());
        });
        return $proceed($attribute);
    }

    public function aroundDelete(
        \Magento\Catalog\Model\ResourceModel\Attribute $attributeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $attribute
    ) {
        $this->enqueueAndNotify('attribute', 'delete', $attribute->getId());
        return $proceed($attribute);
    }

}