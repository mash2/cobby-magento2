<?php
namespace Mash2\Cobby\Model\Import\Product;


class UrlManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductUrlManagementInterface
{

    const ERROR_DUPLICATE_URL_KEY = 'duplicatedUrlKey';
    /**
     * Url key attribute code
     */
    const URL_KEY = 'url_key';

    /**
     * Column product name.
     */
    const COL_NAME = 'name';


    /** @var array */
    protected $productUrlSuffix = [];

    /** @var array */
    protected $productUrlKeys = [];

    /** @var \Magento\Catalog\Model\Product\Url */
    protected $productUrl;

    /** @var array */
    protected $urlKeys = [];

    /** @var false|\Magento\Eav\Model\Entity\Attribute\AbstractAttribute  */
    private $urlKeyAttribute ;

    /**
     * Store manager instance.
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Store ID to its website stores IDs.
     *
     * @var array
     */
    protected $storeIdToWebsiteStoreIds = array();

    /**
     * constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Mash2\Cobby\Helper\Settings $settings
     * @param \Magento\Catalog\Model\Product\Url $productUrl
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Catalog\Model\Product\Url $productUrl,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mash2\Cobby\Model\Product $product
    ) {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->settings = $settings;
        $this->productUrl = $productUrl;
        $this->urlKeyAttribute = $resourceFactory->create()->getAttribute('url_key');
        $this->storeManager = $storeManager;
        $this->initStores();
    }

    /**
     * Initialize stores hash.
     *
     * @return $this
     */
    private function initStores()
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            $this->storeIdToWebsiteStoreIds[$store->getId()] = $store->getWebsite()->getStoreIds();
        }
        return $this;
    }

    public function import($rows)
    {
        $result = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();
        $this->eventManager->dispatch('cobby_import_product_url_import_before', array( 'products' => $productIds ));

        $attributesData = array();
        foreach($rows as $productId => $rowData) {
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $attributesData[$productId] = $this->prepareUrlAttributes($productId, $rowData);
            $changedProductIds[] = $productId;
        }

        $this->saveProductAttributes($attributesData);
        $this->touchProducts($changedProductIds);

        $this->eventManager->dispatch('cobby_import_product_url_import_after', array( 'products' => $changedProductIds ));

        $result = $attributesData;
        return $result;
    }

    private function prepareUrlAttributes($productId, $storeValues)
    {
        $result = array();

        foreach($storeValues as $storeValue)
        {
            if ($this->urlKeyAttribute->getScope() == 'global') {
                $result[0] = $this->formatUrlKey($productId, $storeValue['url_key']);

            }elseif($this->urlKeyAttribute->getScope() == 'website') {
                $storeIds = $this->storeIdToWebsiteStoreIds[$storeValue['store_id']];

                foreach ($storeIds as $storeId) {
                    $result[$storeId] = $this->formatUrlKey($productId, $storeValue['url_key']);
                }

            }else {
                $result[$storeValue['store_id']] = $this->formatUrlKey($productId, $storeValue['url_key']);
            }
        }

        return $result;
    }

    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return $this
     */
    private function saveProductAttributes(array $attributesData)
    {
        $tableName = $this->urlKeyAttribute->getBackendTable();
        $tableData = array();
        $attributeId = $this->urlKeyAttribute->getId();

        foreach ($attributesData as $productId => $storeValues) {
            $linkId = $this->connection->fetchOne(
                $this->connection->select()
                    ->from($this->resourceModel->getTableName('catalog_product_entity'))
                    ->where('entity_id = ?', $productId)
                    ->columns($this->getProductEntityLinkField())
            );

            foreach ($storeValues as $storeId => $storeValue) {
                if ( $storeValue == ProductManagement::COBBY_DEFAULT) {
                    //TODO: evtl delete mit mehreren daten auf einmal
                    /** @var Magento_Db_Adapter_Pdo_Mysql $connection */
                    $this->connection->delete($tableName, array(
                        $this->getProductEntityLinkField() => $linkId,
                        'attribute_id=?'   => (int) $attributeId,
                        'store_id=?'       => (int) $storeId,
                    ));
                } else {
                    $tableData[] = array(
                        $this->getProductEntityLinkField() => $linkId,
                        'attribute_id'   => $attributeId,
                        'store_id'       => $storeId,
                        'value'          => $storeValue
                    );
                }
            }
        }

        if (count($tableData)) {
            $this->connection->insertOnDuplicate($tableName, $tableData, array('value'));
        }

        return $this;
    }

    /**
     * Retrieve product rewrite suffix for store
     *
     * @param int $storeId
     * @return string
     */
    protected function getProductUrlSuffix($storeId = null)
    {
        if (!isset($this->productUrlSuffix[$storeId])) {
            $this->productUrlSuffix[$storeId] = $this->scopeConfig->getValue(
                \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return $this->productUrlSuffix[$storeId];
    }

    private function formatUrlKey($productId, $urlKey) {
        if ( !empty($urlKey) && $urlKey != ProductManagement::COBBY_DEFAULT) {
            $urlKey = $this->productUrl->formatUrlKey($urlKey);

            //TODO: M2
//            if($this->isEnterprise) {
//                $urlKey = $this->_generateNextUrlKeyWithSuffix($productId, $urlKey);
//            }
        }

        return $urlKey;
    }
    
    /**
     * @param array $rowData
     * @return string
     */
    protected function getUrlKey($productId, $rowData)
    {
        if (!empty($rowData[self::URL_KEY])) {
            $this->productUrlKeys[$productId] = $rowData[self::URL_KEY];
        }
        $urlKey = !empty($this->productUrlKeys[$productId])
            ? $this->productUrlKeys[$productId]
            : $this->productUrl->formatUrlKey($rowData[self::COL_NAME]);
        return $urlKey;
    }

    /**
     * Check that url_keys are not assigned to other products in DB
     *
     * @return void
     */
    protected function checkUrlKeyDuplicates()
    {
        $resource = $this->getResource();
        foreach ($this->urlKeys as $storeId => $urlKeys) {
            $urlKeyDuplicates = $this->_connection->fetchAssoc(
                $this->_connection->select()->from(
                    ['url_rewrite' => $resource->getTable('url_rewrite')],
                    ['request_path', 'store_id']
                )->joinLeft(
                    ['cpe' => $resource->getTable('catalog_product_entity')],
                    "cpe.entity_id = url_rewrite.entity_id"
                )->where('request_path IN (?)', array_keys($urlKeys))
                    ->where('store_id IN (?)', $storeId)
                    ->where('cpe.sku not in (?)', array_values($urlKeys))
            );
            foreach ($urlKeyDuplicates as $entityData) {
                $rowNum = $this->rowNumbers[$entityData['store_id']][$entityData['request_path']];
                $this->addRowError(ValidatorInterface::ERROR_DUPLICATE_URL_KEY, $rowNum);
            }
        }
    }
}
