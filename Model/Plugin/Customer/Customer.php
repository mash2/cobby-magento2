<?php
namespace Mash2\Cobby\Model\Plugin\Customer;

class Customer extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{

    public function aroundSave(
        \Magento\Customer\Model\ResourceModel\Group $groupResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $group
    ) {
        $groupResource->addCommitCallback(function () use ($group) {
            $this->enqueueAndNotify('customer_group', 'save', $group->getId());
        });
        return $proceed($group);
    }

    public function aroundDelete(
        \Magento\Customer\Model\ResourceModel\Group $groupResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $group
    ) {
        $this->enqueueAndNotify('customer_group', 'delete', $group->getId());
        return $proceed($group);
    }
}