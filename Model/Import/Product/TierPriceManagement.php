<?php
namespace Mash2\Cobby\Model\Import\Product;

class TierPriceManagement extends AbstractManagement
    implements \Mash2\Cobby\Api\ImportProductTierPriceManagementInterface
{
    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    private $customerGroupCollection;

    /**
     * ImportProductCategoryManagement constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupCollection
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupCollection,
        \Mash2\Cobby\Model\Product $product
    ) {
        $this->customerGroupCollection = $customerGroupCollection;
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
    }

    public function import($rows)
    {
        $result = array();
        $tableName = $this->resourceModel->getTableName('catalog_product_entity_tier_price');

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $this->eventManager->dispatch('cobby_import_product_tierprice_import_before', array( 'products' => $productIds ));

        $groupIds = array();
        foreach ($this->customerGroupCollection as $group) {
            $groupIds[] = $group->getId();
        }

        $tierPricesIn  = array();
        $delProductIds = array();
        $touchedProductIds = array();

        foreach ($rows as $productId => $productPriceItems) {
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $delProductIds[] = $productId;

            foreach ($productPriceItems as $productPriceItem) {
                if (!in_array($productPriceItem['customer_group_id'], $groupIds) && $productPriceItem['all_groups'] != "1") {
                    continue;
                }

                $productPriceItem['entity_id'] = $productId;
                $tierPricesIn[] = $productPriceItem;
            }
        }

        if (count($delProductIds) > 0) {
            $this->connection->delete($tableName, $this->connection->quoteInto('entity_id IN (?)', $delProductIds));
        }

        if (count($tierPricesIn) > 0) {
            $this->connection->insertOnDuplicate($tableName, $tierPricesIn, array('value'));
        }

        $this->touchProducts($touchedProductIds);

        $this->eventManager->dispatch('cobby_import_product_tierprice_import_after', array( 'products' => $productIds ));

        return $result;
    }
}
