<?php
namespace Mash2\Cobby\Model\Queue\Plugin;

class Category extends \Mash2\Cobby\Model\Queue\Plugin\AbstractPlugin
{
    public function aroundSave(
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            $this->enqueueAndNotify('category', 'save', $category->getId());
        });

        return $proceed($category);
    }

    public function aroundDelete(
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $category
    ) {
        $this->enqueueAndNotify('category', 'delete', $category->getId());
        return $proceed($category);
    }
}