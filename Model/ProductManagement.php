<?php
namespace Mash2\Cobby\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Inventory\Model\ResourceModel\SourceItem\CollectionFactory;

class ProductManagement implements \Mash2\Cobby\Api\ProductManagementInterface
{

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceModel;

    /**
     * Product collection factory
     *
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * Json Helper
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var Product
     */
    private $product;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $_eventManager;

    private $_sourceItemProcessor;

    private $_searchCriteriaBuilderFactory;

    private $sourceItemRepository;
    private $sourceItemCollectionFactory;


    /**
     * ProductManagement constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param Product $product
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor $sourceItemsProcessor,
        \Magento\Framework\Api\SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        \Magento\InventoryApi\Api\SourceItemRepositoryInterface $sourceItemRepository,
        \Magento\Inventory\Model\ResourceModel\SourceItem\CollectionFactory $sourceItemCollectionFactory,
        \Mash2\Cobby\Model\Product $product
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->registry = $registry;
        $this->resourceModel = $resourceModel;
        $this->_eventManager = $eventManager;
        $this->_sourceItemProcessor = $sourceItemsProcessor;
        $this->product = $product;
        $this->_searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sourceItemRepository = $sourceItemRepository;
        $this->sourceItemCollectionFactory = $sourceItemCollectionFactory;
    }

    public function getList($pageNum, $pageSize)
    {
        $this->_eventManager->dispatch('cobby_catalog_product_export_ids_before');

        /** @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
        $collection = $this->productCollectionFactory->create();

        $items = $collection
            ->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID)
            ->setPage($pageNum, $pageSize)
            ->load();

        $result =  $items->toArray(array('entity_id', 'sku', 'type_id'));

        $this->_eventManager->dispatch('cobby_catalog_product_export_ids_after');

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function updateSkus($jsonData)
    {
        $this->registry->register('is_cobby_import', 1);

        $rows = $this->jsonHelper->jsonDecode($jsonData);

        $result = array();

        $productIds = array();

        foreach($rows as $row) {
            $productIds[] = $row['product_id'];
        }

        $collection = $this->productCollectionFactory->create();
        $items = $collection
            ->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID)
            ->addFieldToFilter('entity_id', $productIds)
            ->load();

        /*
        if (empty($sourceItems->getItems())) {
            foreach ($items->getItems() as $item) {
                $skus[] = $item->getSku();
            }
            $sourceItemCollection = $this->sourceItemCollectionFactory->create();
            $sourceItems = $sourceItemCollection->addFieldToFilter('sku', $skus)->load();
        }
        */

        foreach($rows as $row) {
            $productId = $row['product_id'];
            $sku = $row['sku'];
            $changed = false;
            $triggerNew = false;
            $oldData = array();

            /*$sourceItemCollection = $this->sourceItemCollectionFactory->create();
            $sourceItems = $sourceItemCollection->addFieldToFilter('sku', $sku)->load();

            if (empty($sourceItems->getItems())) {
                $triggerNew = true;
            }
            else {
                foreach ($sourceItems->getItems() as $sourceItem) {
                    if ($sourceItem->getSourceCode() != 'default')
                        $oldData[$sourceItem->getSourceCode()] = $sourceItem->getData();
                }
            }*/

            if (!empty($sku)) {
                $data = $items->getItems()[$productId]->getData();

                //$oldSku = $data['sku'];

                if ($data['sku'] != null && $data['sku'] !== $sku) {
                    $data['sku'] = $sku;
                    $items->getItems()[$productId]->setData($data);
                    $items->save();
                    $changed = true;
                }

                $sourceItemData = array();

                //$sourceItemCollection = $this->sourceItemCollectionFactory->create();
                /*$sourceItems = $sourceItemCollection->addFieldToFilter('sku', $sku)->load();

                if ($triggerNew) {
                    //$sourceItems = $sourceItemCollection->addFieldToFilter('sku', $oldSku)->load();
                    $itemId = '';
                    $newSourceItemData = array();
                    foreach ($sourceItems->getItems() as $sourceItem) {
                        if ($sourceItem->getSourceCode() == 'default') {
                            $itemId = $sourceItem->getSourceItemId();
                        }
                        else {
                            $data = $sourceItem->getData();
                            $data['source_item_id'] = (string)((int)$itemId + 2);
                            $data['sku'] = $sku;
                            $sourceItemData[] = $data;
                        }
                    }
                }
                else {
                    foreach ($sourceItems->getItems() as $sourceItem) {
                        if ($sourceItem->getSourceCode() != 'default') {
                            $data = $sourceItem->getData();
                            $data['quantity'] = $oldData[$sourceItem->getSourceCode()]['quantity'];
                            $newSourceItemData[] = $data;
                        }
                    }
                }

                $sourceItems->clear();
                $newSourceItem = $sourceItems->getNewEmptyItem();
                $newSourceItem->setData($sourceItemData);
                $sourceItems->addItem($newSourceItem);
                $sourceItems->save();*/

                //$sources = $this->getCurrentSourceItemsMap($oldSku);
                //$this->_sourceItemProcessor->process($sku, $sources);

            }
            $result[] = array('product_id' => $productId, 'sku'  => $sku, 'changed' => $changed);
        }

        //$this->_sourceItemProcessor->process($sku, $sources);

        return $result;
    }

    private function getCurrentSourceItemsMap(string $sku): array
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->_searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteriaBuilder->addFilter(ProductInterface::SKU, $sku)->create();
        $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();

        $sourceItemMap = [];
        if ($sourceItems) {
            foreach ($sourceItems as $sourceItem) {
                $sourceItemMap[$sourceItem->getSourceCode()] = $sourceItem;
            }
        }
        return $sourceItemMap;
    }

    public function updateWebsites($jsonData)
    {
        $this->registry->register('is_cobby_import', 1);

        $websiteData = $this->jsonHelper->jsonDecode($jsonData);

        $result = array();

        if ($websiteData) {
            $tableName = $this->resourceModel->getTableName('catalog_product_website');
            $connection = $this->resourceModel->getConnection();

            //TODO: check for existing product ids
            //TODO: check for existing website_ids
            $productIds = array_keys($websiteData);

            foreach ($websiteData as $productId => $websites) {
                $item = array(
                    'product_id'    => $productId,
                    'added'         => array(),
                    'removed'       => array()
                );

                if ($websites['add']) {
                    foreach ($websites['add'] as $websiteId) {
                        $websitesData[] = array(
                            'product_id'    => $productId,
                            'website_id'    => $websiteId
                        );
                    }

                    $connection->insertOnDuplicate($tableName, $websitesData);

                    $item['added'] = $websites['add'];
                }
                if ($websites['remove']) {
                    $connection->delete(
                        $tableName,
                        array($connection->quoteInto('product_id = ?', $productId),
                            $connection->quoteInto('website_id IN (?)', $websites['remove'])
                        )
                    );

                    $item['removed'] = $websites['remove'];
                }
                $result[] = $item;
            }
        }

        $this->product->updateHash($productIds);

        return $result;
    }
}
