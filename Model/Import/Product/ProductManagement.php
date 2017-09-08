<?php
namespace Mash2\Cobby\Model\Import\Product;

use Magento\Framework\Stdlib\DateTime;

/**
 * Class ProductManagement
 * @package Mash2\Cobby\Model\Import\Product
 */
class ProductManagement extends AbstractManagement// \Magento\CatalogImportExport\Model\Import\Product
    implements \Mash2\Cobby\Api\ImportProductManagementInterface
{

    const COBBY_DEFAULT = '[Use Default Value]';

    //TODO: M2 Move to helper
    const COL_SKU = 'sku';
    const COL_PRODUCT_ID = 'entity_id';
    const COL_TYPE = 'product_type';
    const COL_ATTR_SET = 'attribute_set';
    const COL_ATTRIBUTES = 'attributes';
    const COL_PRODUCT_WEBSITES = 'websites';

    /**
     * Data row scopes.
     */
    const SCOPE_DEFAULT = 1;

    const SCOPE_WEBSITE = 2;

    const SCOPE_STORE = 0;

    const SCOPE_NULL = -1;

    const TYPE_MODELS = 'type_models';

    const PRODUCT_TYPE = 'product_type';

    const USED_SKUS = 'used_skus';

    /**
     * Dry-runned products information from import file.
     *
     * [SKU] => array(
     *     'type_id'        => (string) product type
     *     'attr_set_id'    => (int) product attribute set ID
     *     'entity_id'      => (int) product ID (value for new products will be set after entity save)
     *     'attr_set_code'  => (string) attribute set code
     * )
     *
     * @var array
     */
    protected $newSkus = array();

    /**
     * Existing products SKU-related information in form of array:
     *
     * [SKU] => array(
     *     'type_id'        => (string) product type
     *     'attr_set_id'    => (int) product attribute set ID
     *     'entity_id'      => (int) product ID
     *     'supported_type' => (boolean) is product type supported by current version of import module
     * )
     *
     * @var array
     */
    protected $oldSkus = array();

    /**
     * Attribute cache
     *
     * @var array
     */
    protected $attributeCache = array();

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_localeDate;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * Pairs of attribute set ID-to-name.
     *
     * @var array
     */
    protected $_attrSetIdToName = []; //TODO: M2 obselete, später wird id übergeben

    /**
     * Pairs of attribute set name-to-ID.
     *
     * @var array
     */
    protected $_attrSetNameToId = []; //TODO: M2 obselete, später wird id übergeben

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory
     */
    protected $_setColFactory; //TODO: M2 obselete, später wird id übergeben


    /**
     * @var \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel
     */
    protected $_resource;
    /**
     * @var \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory
     */
    private $proxyProdFactory;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Product\StoreResolver
     */
    private $storeResolver;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Mash2\Cobby\Model\Product
     */
    private $product;

    /**
     * Product entity identifier field
     *
     * @var string
     */
    private $productEntityIdentifierField;

    /**
     * @var \Mash2\Cobby\Helper\Queue
     */
    private $queue;

    /**
     * ImportProductManagement constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory
     * @param DateTime\TimezoneInterface $localeDate
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param DateTime $dateTime
     * @param \Mash2\Cobby\Model\Product $product
     * @param \Mash2\Cobby\Helper\Queue $queue
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
        \Magento\Eav\Model\Config $config,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        DateTime $dateTime,
        \Mash2\Cobby\Model\Product $product,
        \Mash2\Cobby\Helper\Queue $queue
    ) {
        $this->_setColFactory = $setColFactory;
        $this->_resource  = $resourceFactory->create();
        $this->_localeDate = $localeDate;
        $this->dateTime = $dateTime;
        $this->proxyProdFactory = $proxyProdFactory;
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);

        $entityType = $config->getEntityType('catalog_product');
        $this->initAttributeSets($entityType);
        $this->storeResolver = $storeResolver;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->queue = $queue;
    }

    protected function getProductIds()
    {
        $result = array();

        foreach ($this->newSkus as $sku => $data) {
            $result[] = $data['entity_id'];
        }

        return $result;
    }

    /**
     * Initialize attribute sets code-to-id pairs.
     * TODO: M2 entfernen
     *
     * @return $this
     */
    protected function initAttributeSets($entityType)
    {
        foreach ($this->_setColFactory->create()->setEntityTypeFilter($entityType->getEntityTypeId()) as $attributeSet) {
            $this->_attrSetNameToId[$attributeSet->getAttributeSetName()] = $attributeSet->getId();
            $this->_attrSetIdToName[$attributeSet->getId()] = $attributeSet->getAttributeSetName();
        }
        return $this;
    }

    /**
     * Initialize existent product SKUs.
     *
     * @param $filterSkus filter skus
     * @return $this
     */
    protected function initSkus($filterSkus)
    {
        $columns = array('entity_id', 'type_id', 'attribute_set_id', 'sku');
        $productTable = $this->resourceModel->getTableName('catalog_product_entity');

        $select = $this->connection->select()
            ->from($productTable, $columns)
            ->where('sku in (?)', $filterSkus);

        foreach ($this->connection->fetchAll($select) as $info) {
            $typeId = $info['type_id'];
            $sku = $info['sku'];
            $this->oldSkus[$sku] = array(
                'type_id'        => $typeId,
                'attr_set_id'    => $info['attribute_set_id'],
                'entity_id'      => $info['entity_id'],
//                'supported_type' => isset($this->_productTypeModels[$typeId]) //TODO: M2
            );
        }

        return $this;
    }

    /**
     * @param array $rows
     * @return array
     */
    public function import($rows)
    {
        $result = array();

        $productIds = array();
        $skus = array();
        $data = $rows;

        foreach ($rows as $row) {
            if (isset($row[self::COL_PRODUCT_ID])) {
                $productIds[] = $row[self::COL_PRODUCT_ID];
            }
            $skus[] = $row[self::COL_SKU];
            $data[self::TYPE_MODELS] = array($row[self::PRODUCT_TYPE]);
        }

        if ($skus) {
            $this->initSkus($skus);

            $data[self::USED_SKUS] = array_values($skus);

            $transportObject = new \Magento\Framework\DataObject();
            $transportObject->setData($data);

            $this->eventManager->dispatch('cobby_import_product_import_before', array(
                'transport' => $transportObject));

            $this->saveProducts($rows);

            $this->eventManager->dispatch('cobby_import_product_import_after', array('transport' => $transportObject));
        }

        foreach ($this->newSkus as $sku => $data) {
            $data['sku'] = $sku;
            $result[] = $data;
        }

        return $result;
    }

    /**
     * Validate data row.
     *
     * @param array $rowData
     * @return boolean
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function validateRow(array $rowData)
    {
//        if (isset($this->_validatedRows[$rowNum])) {
//            // check that row is already validated
//            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
//        }
//        $this->_validatedRows[$rowNum] = true;
//
//        $rowScope = $this->getRowScope($rowData);
//
//        // BEHAVIOR_DELETE and BEHAVIOR_REPLACE use specific validation logic
//        if (Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
//            if (self::SCOPE_DEFAULT == $rowScope && !isset($this->_oldSku[$rowData[self::COL_SKU]])) {
//                $this->addRowError(ValidatorInterface::ERROR_SKU_NOT_FOUND_FOR_DELETE, $rowNum);
//                return false;
//            }
//        }
//        if (Import::BEHAVIOR_DELETE == $this->getBehavior()) {
//            if (self::SCOPE_DEFAULT == $rowScope && !isset($this->_oldSku[$rowData[self::COL_SKU]])) {
//                $this->addRowError(ValidatorInterface::ERROR_SKU_NOT_FOUND_FOR_DELETE, $rowNum);
//                return false;
//            }
//            return true;
//        }
//
//        if (!$this->validator->isValid($rowData)) {
//            foreach ($this->validator->getMessages() as $message) {
//                $this->addRowError($message, $rowNum);
//            }
//        }
//
        $sku = $rowData[self::COL_SKU];
//        if (null === $sku) {
//            $this->addRowError(ValidatorInterface::ERROR_SKU_IS_EMPTY, $rowNum);
//        } elseif (false === $sku) {
//            $this->addRowError(ValidatorInterface::ERROR_ROW_IS_ORPHAN, $rowNum);
//        } elseif (self::SCOPE_STORE == $rowScope
//            && !$this->storeResolver->getStoreCodeToId($rowData[self::COL_STORE])
//        ) {
//            $this->addRowError(ValidatorInterface::ERROR_INVALID_STORE, $rowNum);
//        }
//
//        // SKU is specified, row is SCOPE_DEFAULT, new product block begins
//        $this->_processedEntitiesCount++;
//
//        $sku = $rowData[self::COL_SKU];
//
        if (isset($this->oldSkus[$sku])) {
            // can we get all necessary data from existent DB product?
            // check for supported type of existing product
//            if (isset($this->_productTypeModels[$this->_oldSku[$sku]['type_id']])) {
                $this->newSkus[$sku] = array(
                    'entity_id' => $this->oldSkus[$sku]['entity_id'],
                    'type_id' => $this->oldSkus[$sku]['type_id'],
                    'attr_set_id' => $this->oldSkus[$sku]['attr_set_id'],
                    'attr_set_code' => $this->_attrSetIdToName[$this->oldSkus[$sku]['attr_set_id']],
                );
            }// else {
//                $this->addRowError(ValidatorInterface::ERROR_TYPE_UNSUPPORTED, $rowNum);
//                // child rows of legacy products with unsupported types are orphans
//                $sku = false;
//            }
//        } else {
//            // validate new product type and attribute set
//            if (!isset($rowData[self::COL_TYPE]) || !isset($this->_productTypeModels[$rowData[self::COL_TYPE]])) {
//                $this->addRowError(ValidatorInterface::ERROR_INVALID_TYPE, $rowNum);
//            } elseif (!isset(
//                    $rowData[self::COL_ATTR_SET]
//                ) || !isset(
//                    $this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]]
//                )
//            ) {
//                $this->addRowError(ValidatorInterface::ERROR_INVALID_ATTR_SET, $rowNum);
//            } elseif (is_null($this->skuProcessor->getNewSku($sku))) {
//                $this->skuProcessor->addNewSku(
//                    $sku,
//                    [
//                        'entity_id' => null,
//                        'type_id' => $rowData[self::COL_TYPE],
//                        'attr_set_id' => $this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]],
//                        'attr_set_code' => $rowData[self::COL_ATTR_SET],
//                    ]
//                );
//            }
//            if ($this->getErrorAggregator()->isRowInvalid($rowNum)) {
//                // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
//                $sku = false;
//            }
//        }
//
//        if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
//            $newSku = $this->skuProcessor->getNewSku($sku);
//            // set attribute set code into row data for followed attribute validation in type model
//            $rowData[self::COL_ATTR_SET] = $newSku['attr_set_code'];
//
//            $rowAttributesValid = $this->_productTypeModels[$newSku['type_id']]->isRowValid(
//                $rowData,
//                $rowNum,
//                !isset($this->_oldSku[$sku])
//            );
//            if (!$rowAttributesValid && self::SCOPE_DEFAULT == $rowScope) {
//                // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
//                $sku = false;
//            }
//        }
//        // validate custom options
//        $this->getOptionEntity()->validateRow($rowData, $rowNum);
//        if (!empty($rowData[self::URL_KEY]) || !empty($rowData[self::COL_NAME])) {
//            $urlKey = $this->getUrlKey($rowData);
//            $storeCodes = empty($rowData[self::COL_STORE_VIEW_CODE])
//                ? array_flip($this->storeResolver->getStoreCodeToId())
//                : explode($this->getMultipleValueSeparator(), $rowData[self::COL_STORE_VIEW_CODE]);
//            foreach ($storeCodes as $storeCode) {
//                $storeId = $this->storeResolver->getStoreCodeToId($storeCode);
//                $productUrlSuffix = $this->getProductUrlSuffix($storeId);
//                $urlPath = $urlKey . $productUrlSuffix;
//                if (empty($this->urlKeys[$storeId][$urlPath])
//                    || ($this->urlKeys[$storeId][$urlPath] == $rowData[self::COL_SKU])
//                ) {
//                    $this->urlKeys[$storeId][$urlPath] = $rowData[self::COL_SKU];
//                    $this->rowNumbers[$storeId][$urlPath] = $rowNum;
//                } else {
//                    $this->addRowError(ValidatorInterface::ERROR_DUPLICATE_URL_KEY, $rowNum);
//                }
//            }
//        }
//        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        return true;
    }

    /**
     * Retrieve attribute by code
     *
     * @param string $attrCode
     * @return mixed
     */
    public function getAttributeByCode($attrCode)
    {
        if (!isset($this->attributeCache[$attrCode])) {
            $this->attributeCache[$attrCode] = $this->_resource->getAttribute($attrCode);
        }
        return $this->attributeCache[$attrCode];
    }

    private function prepareAttributesWithDefaultValueForSave(array $rowData, $withDefaultValue = true)
    {
        $result = array();

        foreach ($rowData as $attrCode => $attrParams) {
            $attribute = $this->getAttributeByCode($attrCode);
            if (!$attribute) {
                continue;
            }
            $attrParams = $attribute->getData();
            if (!$attribute->isStatic()) {
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $result[$attrCode] = $rowData[$attrCode];
                } elseif (array_key_exists($attrCode, $rowData)) {
                    if ( !$withDefaultValue || $rowData[$attrCode] != "" || $attrCode == 'url_key' )
                    {
                        $result[$attrCode] = $rowData[$attrCode];
                    }
                } elseif (null !== $attrParams['default_value'] && $withDefaultValue) {
                    if ($withDefaultValue) {
                        $result[$attrCode] = $attrParams['default_value'];
                    }
                }
            }
        }


        return $this->_prepareRowForDb($result);
    }

    protected function saveProducts($rows)
    {
        $entityRowsIn = [];
        $attributes = [];
        $productWebsites = [];
        $existingStoreIds = array_keys($this->storeManager->getStores(true));
        $productIds = array();

        foreach ($rows as $row) {
            if (!$this->validateRow($row)) {
                continue;
            }

            $sku = $row[self::COL_SKU];
            $productType = $row[self::COL_TYPE];
//            $attributeSetId = $row['attribute_set_id'];
            $attributeSet = $row['attribute_set'];

            // Entity phase
            if (!isset($this->oldSkus[$sku])) {
                //new row
                $entityRowsIn[$sku] = [
                    'attribute_set_id' => $this->_attrSetNameToId[$attributeSet],
                    'type_id' => $productType,
                    'sku' => $sku,
                    'has_options' => isset($row['has_options']) ? $row['has_options'] : 0,
                    'created_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                    'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                ];

                //TODO: M2
                //aktuell nur bei neuanlage, evtl auch später für änderungen um nicht über mass action zu gehen
                $websiteCodes = $row[self::COL_PRODUCT_WEBSITES];
                $productWebsites[$sku] = array();
                foreach ($websiteCodes as $websiteCode) {
                    $websiteId = $this->storeResolver->getWebsiteCodeToId($websiteCode);
                    $productWebsites[$sku][] = $websiteId ;
                }
            } else {
                $productIds[] = $this->oldSkus[$sku]['entity_id'];
            }

            // Attributes phase
//            $productTypeModel = $this->_productTypeModels[$productType];
            $attributeRows = $row['attributes'];
            foreach ($attributeRows as $attrRow) {
                $storeId = $attrRow['store_id'];
                unset($attrRow['store_id']);

                if (!in_array($storeId, $existingStoreIds)) {
                    continue;
                }

//            if (!empty($row['tax_class_name'])) {
//                $row['tax_class_id'] =
//                    $this->taxClassProcessor->upsertTaxClass($row['tax_class_name'], $productTypeModel);
//            }
//

                $attrRow = $this->prepareAttributesWithDefaultValueForSave($attrRow, !isset($this->oldSkus[$sku]));
                $product = $this->proxyProdFactory->create(['data' => $attrRow]);

                foreach ($attrRow as $attrCode => $attrValue) {
                    $attribute = $this->getAttributeByCode($attrCode);
                    if (!$attribute) {
                        continue;
                    }

                    $attrId = $attribute->getId();
                    $backModel = $attribute->getBackendModel();
                    $attrTable = $attribute->getBackend()->getTable();
                    $storeIds  = array(0);

                    if($attrValue != self::COBBY_DEFAULT) {
                        if ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                            $attrValue = $this->dateTime->gmDate(
                                'Y-m-d H:i:s',
                                $this->_localeDate->date($attrValue)->getTimestamp()
                            );
                        } elseif ($backModel) {
                            $attribute->getBackend()->beforeSave($product);
                            $attrValue = $product->getData($attribute->getAttributeCode());
                        }
                    }

                    if ($storeId != \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
                        if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                            // check website defaults already set
                            if (!isset($attributes[$attrTable][$sku][$attrId][$storeId])) {
                                $storeIds = $this->storeResolver->getStoreIdToWebsiteStoreIds($storeId);
                            }
                        } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                            $storeIds = array($storeId);
                        }
                    }

                    foreach ($storeIds as $storeId) {
                        $attributes[$attrTable][$sku][$attrId][$storeId] = $attrValue;
                    }

                    // restore 'backend_model' to avoid 'default' setting
                    $attribute->setBackendModel($backModel);
                }
            }
        }

        $this->saveProductEntity($entityRowsIn)
//            ->touchProducts($this->getProductIds())
            ->saveProductWebsites($productWebsites)
            ->saveProductAttributes($attributes);

        if (!empty($productIds)){
            $this->product->updateHash($productIds);
            $this->queue->enqueueAndNotify('product', 'save', $productIds);
        }

        return $this;
    }

    /**
     * Change row data before saving in DB table.
     *
     * @param array $rowData
     * @return array
     */
    protected function _prepareRowForDb(array $rowData)
    {
        /**
         * Convert all empty strings to null values, as
         * a) we don't use empty string in DB
         * b) empty strings instead of numeric values will product errors in Sql Server
         */
        foreach ($rowData as $key => $val) {
            if ($val === '') {
                $rowData[$key] = null;
            }
        }
        return $rowData;
    }

    /**
     * insert new products in entity table.
     *
     * @param array $entityRowsIn Row for insert
     * @return $this
     */
    protected function saveProductEntity(array $entityRowsIn)
    {
        if ($entityRowsIn) {
            $entityTable = $this->resourceModel->getTableName('catalog_product_entity');

            //
            $metadata = $this->getMetadataPool()->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class);

            $insertRows = [];
            foreach ($entityRowsIn as $key => $insertRow) {

                if ($this->getProductEntityLinkField() == 'row_id' && empty($insertRow[$metadata->getIdentifierField()])) {
                    $insertRow[$metadata->getIdentifierField()] = $metadata->generateIdentifier();
                    $insertRow['created_in'] = 1;
                    $insertRow['updated_in'] = 2147483647; //\Magento\Staging\Model\VersionManager::MAX_VERSION;
                }
                $insertRows[$key] = $insertRow;
            }

            $this->connection->insertMultiple($entityTable, $insertRows);


            $newProducts = $this->connection->fetchPairs(
                $this->connection->select()
                ->from($entityTable, ['sku', 'entity_id'])
                ->where('sku IN (?)', array_keys($entityRowsIn))
            );
            foreach ($newProducts as $sku => $newId) {
                // fill up entity_id for new products
                $this->newSkus[$sku]['entity_id'] = $newId;
            }
        }
        return $this;
    }

    /**
     * Save product websites.
     *
     * @param array $websiteData
     * @return $this
     */
    protected function saveProductWebsites(array $websiteData)
    {
        if ($websiteData) {
            $tableName = $this->resourceModel->getTableName('catalog_product_website');

            $websitesData = [];
            $delProductId = [];

            foreach ($websiteData as $delSku => $websites) {
                $productId = $this->newSkus[$delSku]['entity_id'];
                $delProductId[] = $productId;

                foreach (array_values($websites) as $websiteId) {
                    $websitesData[] = ['product_id' => $productId, 'website_id' => $websiteId];
                }
            }
            //TODO: M2
//            if (Import::BEHAVIOR_APPEND != $this->getBehavior()) {
//                $this->connection->delete(
//                    $tableName,
//                    $this->connection->quoteInto('product_id IN (?)', $delProductId)
//                );
//            }
            if ($websitesData) {
                $this->connection->insertOnDuplicate($tableName, $websitesData);
            }
        }
        return $this;
    }

    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return $this
     */
    protected function saveProductAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $skuData) {
            $tableData = [];
            foreach ($skuData as $sku => $attributes) {
                $productId = $this->newSkus[$sku]['entity_id'];
                $linkId = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from($this->resourceModel->getTableName('catalog_product_entity'))
                        ->where('entity_id = ?', $productId)
                        ->columns($this->getProductEntityLinkField())
                );

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        if ( $storeValue == self::COBBY_DEFAULT) {
                            //TODO: evtl delete mit mehreren daten auf einmal
                            if ($storeId != \Magento\Store\Model\Store::DEFAULT_STORE_ID){
                                $this->connection->delete($tableName, array(
                                    $this->getProductEntityLinkField().'=?' => $linkId,
                                    'attribute_id=?'   => (int) $attributeId,
                                    'store_id=?'       => (int) $storeId,
                                ));
                            }
                        }else {
                            $tableData[] = [
                                $this->getProductEntityLinkField() => $linkId,
                                'attribute_id'      => $attributeId,
                                'store_id'          => $storeId,
                                'value'             => $storeValue,
                            ];
                        }
                    }
//                    /*
//                    If the store based values are not provided for a particular store,
//                    we default to the default scope values.
//                    In this case, remove all the existing store based values stored in the table.
//                    */
//                    $where[] = $this->connection->quoteInto(
//                        '(store_id NOT IN (?)',
//                        array_keys($storeValues))
//                        . $this->connection->quoteInto(
//                            ' AND attribute_id = ?',
//                            $attributeId
//                        ) . $this->connection->quoteInto(
//                            ' AND entity_id = ?)',
//                            $productId
//                        );
//                    if (count($where) >= \Magento\CatalogImportExport\Model\Import\Product::ATTRIBUTE_DELETE_BUNCH) {
//                        $this->connection->delete($tableName, implode(' OR ', $where));
//                        $where = [];
//                    }
                }
            }
//            if (!empty($where)) {
//                $this->connection->delete($tableName, implode(' OR ', $where));
//            }

            if($tableData) {
                $this->connection->insertOnDuplicate($tableName, $tableData, ['value']);
            }
        }
        return $this;
    }


}
