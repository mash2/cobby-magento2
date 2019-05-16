<?php
namespace Mash2\Cobby\Model\Import\Product;

/**
 * Class StockManagement
 * @package Mash2\Cobby\Model\Import\Product
 */
class StockManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductStockManagementInterface
{
    /**
     * @var array
     */
    protected $defaultStockData = [
        'manage_stock' => 1,
        'use_config_manage_stock' => 1,
        'qty' => 0,
        'min_qty' => 0,
        'use_config_min_qty' => 1,
        'min_sale_qty' => 1,
        'use_config_min_sale_qty' => 1,
        'max_sale_qty' => 10000,
        'use_config_max_sale_qty' => 1,
        'is_qty_decimal' => 0,
        'backorders' => 0,
        'use_config_backorders' => 1,
        'notify_stock_qty' => 1,
        'use_config_notify_stock_qty' => 1,
        'enable_qty_increments' => 0,
        'use_config_enable_qty_inc' => 1,
        'qty_increments' => 0,
        'use_config_qty_increments' => 1,
        'is_in_stock' => 1,
        'low_stock_date' => null,
        'stock_status_changed_auto' => 0,
        'is_decimal_divided' => 0,
    ];

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    private $stockRegistry;
    /**
     * @var \Magento\CatalogInventory\Api\StockConfigurationInterface
     */
    private $stockConfiguration;
    /**
     * @var \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface
     */
    private $stockStateProvider;

    private $cobbySettings;

    private $commandAppend;

    private $commandDelete;

    private $productMetadata;

    private $stockItemRepo;
    private $stockItem;

    /**
     * StockManagement constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration
     * @param \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider
     * @param \Mash2\Cobby\Helper\Settings $cobbySettings
     * @internal param \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider,
        \Mash2\Cobby\Helper\Settings $cobbySettings,
        \Mash2\Cobby\Model\Product $product,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepo,
        \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->stockConfiguration = $stockConfiguration;
        $this->stockStateProvider = $stockStateProvider;
        $this->cobbySettings = $cobbySettings;
        $this->productMetadata = $productMetadata;
        $this->stockItemRepo = $stockItemRepo;
        $this->stockItem = $stockItem;
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
    }


    public function import($rows)
    {
        $result = array();
        $productIds = array_keys($rows);
        $this->eventManager->dispatch('cobby_import_product_stock_import_before', array( 'products' => $productIds ));

        $manageStock = $this->cobbySettings->getManageStock();
        $defaultQuantity = $this->cobbySettings->getDefaultQuantity();
        $defaultAvailability = $this->cobbySettings->getDefaultAvailability();

        $existingProductIds = $this->loadExistingProductIds($productIds);

        $entityTable = $this->resourceModel->getTableName('cataloginventory_stock_item');
        $stockItems = array();

        $inventorySourceAppendItems = array();
        $inventorySourceDeleteItems = array();

        $multiSources = version_compare($this->productMetadata->getVersion(), "2.3.0", ">=");

        foreach ($rows as $row) {
            $sku = $row['sku'];
            unset($row['sku']);
            $productId = $row['product_id'];
            unset($row['product_id']);

            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            if (!empty($row['inventory_sources']) && $multiSources) {

                foreach( $row['inventory_sources'] as $inventorySource ) {
                    if($inventorySource[self::OBJECT_STATE] == self::DELETED ) {
                        $inventorySourceDeleteItems[] = $inventorySource;
                    } else {
                        $inventorySourceAppendItems[] = $inventorySource;
                    }
                }
            }

            unset($row['inventory_sources']);

            $websiteId = $this->stockConfiguration->getDefaultScopeId();
            $stockData = array(

                'product_id' => $productId,
                'website_id' => $websiteId,
            );

            $stockData['stock_id'] = $this->stockRegistry->getStock($websiteId)->getStockId();

            $stockItemDo = $this->stockRegistry->getStockItem($productId, $websiteId);
            $existStockData = $stockItemDo->getData();

            if ($manageStock == \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_ENABLED){
                $stockData = array_merge(
                    $stockData,
                    $this->defaultStockData,
                    array_intersect_key($existStockData, $this->defaultStockData),
                    array_intersect_key($row, $this->defaultStockData)
                );
            } elseif (( $manageStock == \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_READONLY ||
                        $manageStock == \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_DISABLED) &&
                        !$existStockData){
                $defaultStock = array();

                $defaultStock['qty'] = $defaultQuantity;
                $defaultStock['is_in_stock'] = $defaultAvailability;

                $stockData = array_merge(
                    $stockData,
                    $this->defaultStockData,
                    $defaultStock
                );

                if ($multiSources) {
                    $defaultSource = array(
                        'source_code' => 'default',
                        'quantity' => $defaultQuantity,
                        'status' => $defaultAvailability,
                        'sku'   => $sku
                    );

                    $inventorySourceAppendItems[] = $defaultSource;
                }
            }

            $stockItemDo->setData($stockData);

            $stockItems[] = $stockItemDo->getData();
        }

        if (!empty($stockItems)) {
            foreach ($stockItems as $stockItem) {
                $stockItem['item_id'] = $stockItem['product_id'];
                $stockItem['type_id'] = 'simple';
                $this->stockItem->setData($stockItem);
                $this->stockItemRepo->save($this->stockItem);
            }
        }

        /*
            // Insert rows
        if (!empty($stockItems)) {
            $this->connection->insertOnDuplicate($entityTable, array_values($stockItems));
            $this->touchProducts($existingProductIds);
        }




        if (!empty($inventorySourceAppendItems)) {
            //"This code needs porting or exist for backward compatibility purposes."
            //(https://devdocs.magento.com/guides/v2.2/extension-dev-guide/object-manager.html)
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $this->commandAppend = $objectManager->create('Magento\InventoryImportExport\Model\Import\Command\Append');
            $this->commandAppend->execute($inventorySourceAppendItems);
        }

        if (!empty($inventorySourceDeleteItems)) {
            //"This code needs porting or exist for backward compatibility purposes."
            //(https://devdocs.magento.com/guides/v2.2/extension-dev-guide/object-manager.html)
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $this->commandDelete = $objectManager->create('Magento\InventoryImportExport\Model\Import\Command\Delete');
            $this->commandDelete->execute($inventorySourceDeleteItems);
        }
        */

        $this->eventManager->dispatch('cobby_import_product_stock_import_after', array( 'products' => $productIds ));

        return $result;
    }

    /**
     * Stock item saving.
     *
     * @return $this
     */
    protected function _saveStockItem()
    {
        $indexer = $this->indexerRegistry->get('catalog_product_category');
        /** @var $stockResource \Magento\CatalogInventory\Model\ResourceModel\Stock\Item */
        $stockResource = $this->_stockResItemFac->create();
        $entityTable = $stockResource->getMainTable();
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $stockData = [];
            $productIdsToReindex = [];
            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }

                $row = [];
                $row['product_id'] = $this->skuProcessor->getNewSku($rowData[self::COL_SKU])['entity_id'];
                $productIdsToReindex[] = $row['product_id'];

                $row['website_id'] = $this->stockConfiguration->getDefaultScopeId();
                $row['stock_id'] = $this->stockRegistry->getStock($row['website_id'])->getStockId();

                $stockItemDo = $this->stockRegistry->getStockItem($row['product_id'], $row['website_id']);
                $existStockData = $stockItemDo->getData();

                $row = array_merge(
                    $this->defaultStockData,
                    array_intersect_key($existStockData, $this->defaultStockData),
                    array_intersect_key($rowData, $this->defaultStockData),
                    $row
                );



                if ($this->stockConfiguration->isQty(
                    $this->skuProcessor->getNewSku($rowData[self::COL_SKU])['type_id']
                )) {
                    $stockItemDo->setData($row);
                    $row['is_in_stock'] = $this->stockStateProvider->verifyStock($stockItemDo);
                    if ($this->stockStateProvider->verifyNotification($stockItemDo)) {
                        $row['low_stock_date'] = $this->dateTime->gmDate(
                            'Y-m-d H:i:s',
                            (new \DateTime())->getTimestamp()
                        );
                    }
                    $row['stock_status_changed_auto'] =
                        (int) !$this->stockStateProvider->verifyStock($stockItemDo);
                } else {
                    $row['qty'] = 0;
                }
                if (!isset($stockData[$rowData[self::COL_SKU]])) {
                    $stockData[$rowData[self::COL_SKU]] = $row;
                }
            }

            // Insert rows
            if (!empty($stockData)) {
                $this->_connection->insertOnDuplicate($entityTable, array_values($stockData));
            }

            if ($productIdsToReindex) {
                $indexer->reindexList($productIdsToReindex);
            }
        }
        return $this;
    }
}
