<?php
namespace Mash2\Cobby\Model\Plugin\Store;

class Website extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{
    public function aroundSave(
        \Magento\Store\Model\ResourceModel\Website $websiteResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $website
    ) {
        $websiteResource->addCommitCallback(function () use ($website) {
           $this->resetHash('website_changed');
        });

        return $proceed($website);
    }

    public function aroundDelete(
        \Magento\Store\Model\ResourceModel\Website $websiteResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $website
    ){
        $this->resetHash('website_changed');

        return $proceed($website);
    }

}