<?php

namespace Mash2\Cobby\Model;

class GroupRepository implements \Mash2\Cobby\Api\GroupRepositoryInterface
{
    /**
     * @var \Magento\Store\Model\ResourceModel\Group\CollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @param  \Magento\Store\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory
     */
    public function __construct(
        \Magento\Store\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory
    )
    {
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    public function getList()
    {
        $result = array();
        /** @var \Magento\Store\Model\ResourceModel\Group\Collection $groupCollection */
        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->setLoadDefault(true);

        foreach ($groupCollection as $item) {
            $result[] = array(
                'group_id' => $item->getGroupId(),
                'default_store_id' => $item->getDefaultStoreId(),
                'root_category_id' => $item->getRootCategoryId()
            );
        }
        return $result;
    }
}
