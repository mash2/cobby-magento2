<?php

namespace Mash2\Cobby\Model;

class WebsiteRepository implements \Mash2\Cobby\Api\WebsiteRepositoryInterface
{
    /**
     * @var \Magento\Store\Model\ResourceModel\Website\CollectionFactory
     */
    protected $websiteCollectionFactory;

    /**
     * @param \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
     */
    public function __construct(
        \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
    )
    {
        $this->websiteCollectionFactory = $websiteCollectionFactory;
    }

    public function getList()
    {
        $result = array();

        $collection = $this->websiteCollectionFactory->create();
        $collection->setLoadDefault(true);

        $sortOrder = 0;
        foreach ($collection as $website) {
            $result[] = array(
                'website_id'        => $website->getWebsiteId(),
                'code'              => $website->getCode(),
                'name'              => $website->getName(),
                'default_group_id'  => $website->getDefaultGroupId(),
                'is_default'        => $website->getIsDefault(),
                'sort_order'        => $sortOrder //$website->getSortOrder()
            );
            $sortOrder++;
        }

        return $result;
    }
}
