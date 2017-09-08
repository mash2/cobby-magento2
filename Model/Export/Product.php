<?php

namespace Mash2\Cobby\Model\Export;

use Magento\Framework\App\ResourceConnection;
use \Magento\Store\Model\Store;

/**
 * Class Product
 * @package Mash2\Cobby\Model\Export
 */
class Product extends \Mash2\Cobby\Model\Export\AbstractEntity
    implements \Mash2\Cobby\Api\ExportProductManagementInterface
{
    /**
     * Value that means all entities (e.g. websites, groups etc.)
     */
    const VALUE_ALL = 'all';

    const COL_STORE = '_store';
    const COL_SKU = '_sku';
    const COL_HASH = '_hash';
    const COL_MAGENTO_ID = '_entity_id';
    const COL_TYPE = '_type';
    const COL_ATTR_SET = '_attribute_set';
    const COL_ATTRIBUTES = '_attributes';
    const COL_CATEGORY = '_categories';
    const COL_WEBSITE = '_websites';
    const COL_IMAGE_GALLERY = '_image_gallery';
    const COL_INVENTORY = '_inventory';
    const COL_GROUP_PRICE = '_group_price';
    const COL_TIER_PRICE = '_tier_price';
    const COL_LINKS = '_links';
    const COL_SUPER_PRODUCT_ATTRIBUTES = '_super_product_attributes';
    const COL_SUPER_PRODUCT_SKUS = '_super_product_skus';
    const COL_CUSTOM_OPTIONS = '_custom_options';
    const COL_BUNDLE_OPTIONS = '_bundle_options';

    /**
     * Attribute code to its values. Only attributes with options and only default store values used.
     *
     * @var array
     */
    protected static $attrCodes = null;

    /**
     *  attrs not supported in cobby
     **/
    protected $skipUnsupportedAttrCodes = array('is_recurring', 'recurring_profile', 'category_ids', 'giftcard_amounts');


    /**
     * Json Helper
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * DB connection.
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Array of pairs store ID to its code.
     *
     * @var array
     */
    protected $storeIdToCode = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    protected $_attributeColFactory;

    /**
     * Product collection
     *
     * @var \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected $_entityCollection;

    /**
     * Product collection
     *
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_entityCollectionFactory;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceModel;

    /**
     * @var \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory
     */
    protected $_itemFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Option\Collection
     */
    protected $optionColFactory;

    /**
     * Product entity link field
     *
     * @var string
     */
    protected $productEntityLinkField;

    /**
     * @var \Mash2\Cobby\Model\ResourceModel\Product\CollectionFactory
     */
    private $cobbyProduct;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeColFactory ,
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $itemFactory ,
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\Option\CollectionFactory $optionColFactory
     * @param \Mash2\Cobby\Model\ResourceModel\Product\CollectionFactory $cobbyProduct
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Eav\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeColFactory,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $itemFactory,
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\Option\CollectionFactory $optionColFactory,
        \Mash2\Cobby\Model\ResourceModel\Product\CollectionFactory $cobbyProduct,
        \Magento\Framework\Event\ManagerInterface $eventManager
    )
    {
        $this->_entityCollectionFactory = $collectionFactory;
        $this->localeDate = $localeDate;
        $this->storeManager = $storeManager;
//        $entityCode = $this->getEntityTypeCode();
//        $this->_entityTypeId = $config->getEntityType($entityCode)->getEntityTypeId();
        $this->resourceModel = $resourceModel;
        $this->connection = $resourceModel->getConnection();
        $this->_attributeColFactory = $attributeColFactory;
        $this->_itemFactory = $itemFactory;
        $this->optionColFactory = $optionColFactory;
        $this->jsonHelper = $jsonHelper;
        $this->cobbyProduct = $cobbyProduct;
        $this->eventManager = $eventManager;

        $this->initStores();
    }

    /**
     * Get product entity link field
     *
     * @return string
     */
    protected function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }

    /**
     * Initialize stores hash.
     *
     * @return $this
     */
    protected function initStores()
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            $this->storeIdToCode[$store->getId()] = $store->getCode();
        }
        ksort($this->storeIdToCode);
        // to ensure that 'admin' store (ID is zero) goes first

        return $this;
    }

    protected function _getEntityCollection($resetCollection = false)
    {
        //TODO: disable flat
        if ($resetCollection || empty($this->_entityCollection)) {
            $this->_entityCollection = $this->_entityCollectionFactory->create();
        }
        return $this->_entityCollection;
    }

    /**
     * Entity attributes collection getter.
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    public function getAttributeCollection()
    {
        return $this->_attributeColFactory->create();
    }

    /**
     * Get attributes codes which are appropriate for export.
     *
     * @return array
     */
    protected function _getExportAttrCodes()
    {
        if (null === self::$attrCodes) {
            $attrCodes = array();

            foreach ($this->getAttributeCollection() as $attribute) {
                if (in_array($attribute->getAttributeCode(), $this->skipUnsupportedAttrCodes)) {
                    continue;
                }

                $attrCodes[] = $attribute->getAttributeCode();
            }

            self::$attrCodes = $attrCodes;
        }
        return self::$attrCodes;
    }

    private function _initResult($productIds, $defaultValue = array())
    {
        $result = array();
        foreach ($productIds as $productId) {
            $result[$productId] = $defaultValue;
        }
        return $result;
    }

    private function _prepareAttributes($productIds)
    {
        $result = $this->_initResult($productIds);

        $collection = $this->_getEntityCollection();
        $this->_prepareEntityCollection($collection);

        $exportAttrCodes = $this->_getExportAttrCodes();

        foreach ($this->storeIdToCode as $storeId => $storeCode) {
            $collection->setStoreId($storeId);

            $collection->addAttributeToFilter('entity_id', array('in' => $productIds));

            foreach ($collection as $productId => $product) {
                $attributes = array('store_id' => $storeId);
                foreach ($exportAttrCodes as &$attrCode) { // go through all valid attribute codes
                    $attrValue = $product->getData($attrCode);

                    if (!is_array($attrValue)) {
                        $attributes[$attrCode] = $attrValue;
                    }
                }

                $result[$productId][] = $attributes;
            }

            $collection->clear();
        }

        return $result;
    }

    /**
     * Apply filter to collection and add not skipped attributes to select.
     *
     * @param \Magento\Eav\Model\Entity\Collection\AbstractCollection $collection
     * @return \Magento\Eav\Model\Entity\Collection\AbstractCollection
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _prepareEntityCollection(\Magento\Eav\Model\Entity\Collection\AbstractCollection $collection)
    {

        foreach ($this->getAttributeCollection() as $attribute) { //$this->filterAttributeCollection()
            $attrCode = $attribute->getAttributeCode();
            $exportAttrCodes = $this->_getExportAttrCodes();

            if (in_array($attrCode, $exportAttrCodes)) {
                $collection->addAttributeToSelect($attrCode);
            }
        }
    }

    /**
     * Prepare catalog inventory
     *
     * @param  int[] $productIds
     * @return array
     */
    protected function prepareCatalogInventory(array $productIds)
    {
        $result = $this->_initResult($productIds, null);

        $select = $this->connection->select()->from(
            $this->_itemFactory->create()->getMainTable()
        )->where(
            'product_id IN (?)',
            $productIds
        );

        $stmt = $this->connection->query($select);

        while ($stockItemRow = $stmt->fetch()) {
            $productId = $stockItemRow['product_id'];
            unset(
                $stockItemRow['item_id'],
                $stockItemRow['product_id'],
                $stockItemRow['low_stock_date'],
                $stockItemRow['stock_id'],
                $stockItemRow['stock_status_changed_auto']
            );
            $result[$productId] = $stockItemRow;
        }

        return $result;
    }

    /**
     * Prepare product links
     *
     * @param  array $productIds
     * @return array
     */
    protected function prepareLinks(array $productIds)
    {
        $result = $this->_initResult($productIds);

        $select = $this->connection->select()->from(
            ['cpl' => $this->resourceModel->getTableName('catalog_product_link')],
            ['cpl.product_id', 'cpl.linked_product_id', 'cpe.sku', 'cpl.link_type_id', 'position' => 'cplai.value', 'default_qty' => 'cplad.value']
        )->joinLeft(
            ['cpe' => $this->resourceModel->getTableName('catalog_product_entity')],
            '(cpe.entity_id = cpl.linked_product_id)',
            []
        )->joinLeft(
            ['cpla' => $this->resourceModel->getTableName('catalog_product_link_attribute')],
            $this->connection->quoteInto(
                '(cpla.link_type_id = cpl.link_type_id AND cpla.product_link_attribute_code = ?)', 'position'),
            []
        )->joinLeft(
            ['cplaq' => $this->resourceModel->getTableName('catalog_product_link_attribute')],
            $this->connection->quoteInto(
                '(cplaq.link_type_id = cpl.link_type_id AND cplaq.product_link_attribute_code = ?)', 'qty'),
            []
        )->joinLeft(
            ['cplai' => $this->resourceModel->getTableName('catalog_product_link_attribute_int')],
            '(cplai.link_id = cpl.link_id AND cplai.product_link_attribute_id = cpla.product_link_attribute_id)',
            []
        )->joinLeft(
            ['cplad' => $this->resourceModel->getTableName('catalog_product_link_attribute_decimal')],
            '(cplad.link_id = cpl.link_id AND cplad.product_link_attribute_id = cplaq.product_link_attribute_id)',
            []
        )->where('cpl.product_id IN (?)', $productIds);

        $stmt = $this->connection->query($select);
        while ($linksRow = $stmt->fetch()) {
            $productId = $linksRow['product_id'];
            $result[$productId][] = array(
                'product_id' => $linksRow['linked_product_id'],
                'sku' => $linksRow['sku'],
                'link_type_id' => $linksRow['link_type_id'],
                'position' => $linksRow['position'],
                'default_qty' => $linksRow['default_qty']
            );
        }

        return $result;
    }

    /**
     * Prepare products tier prices
     *
     * @param  array $productIds
     * @return array
     */
    protected function prepareTierPrices(array $productIds)
    {
        $result = $this->_initResult($productIds);

        $productEntityLinkField = $this->getProductEntityLinkField();

        $select = $this->connection->select()
            ->from($this->resourceModel->getTableName('catalog_product_entity_tier_price'))
            ->where($productEntityLinkField . ' IN(?)', $productIds);

        $stmt = $this->connection->query($select);
        while ($tierRow = $stmt->fetch()) {
            $productId = $tierRow[$productEntityLinkField];
            $result[$productId][] = array(
                'all_groups' => $tierRow['all_groups'],
                'customer_group_id' => $tierRow['customer_group_id'],
                'qty' => $tierRow['qty'],
                'value' => $tierRow['value'],
                'website_id' => $tierRow['website_id']
            );
        }

        return $result;
    }


    /**
     * Prepare products media gallery
     *
     * @param  array $productIds
     * @return array
     */
    protected function prepareMediaGallery(array $productIds, $storeIds)
    {
        $result = $this->_initResult($productIds);

        $productEntityLinkField = $this->getProductEntityLinkField();

        $select = $this->connection->select()
            ->from(
                array('mgv' => $this->resourceModel->getTableName('catalog_product_entity_media_gallery_value')),
                array('mgv.' . $productEntityLinkField, 'mgv.store_id', 'mgv.label', 'mgv.position', 'mgv.disabled')
            )->joinLeft(
                array('mg' => $this->resourceModel->getTableName('catalog_product_entity_media_gallery')),
                '(mg.value_id = mgv.value_id)',
                array('mg.attribute_id', 'filename' => 'mg.value', 'mg.media_type')
            )
            ->where('mgv.' . $productEntityLinkField . ' IN (?) ', $productIds);

        $stmt = $this->connection->query($select);
        while ($mediaRow = $stmt->fetch()) {
            $productId = $mediaRow[$productEntityLinkField];
            $storeId = isset($mediaRow['store_id']) ? (int)$mediaRow['store_id'] : 0;
            if (in_array($storeId, $storeIds)) {
                $result[$productId][] = array(
                    'store_id' => $mediaRow['store_id'],
                    'attribute_id' => $mediaRow['attribute_id'],
                    'filename' => $mediaRow['filename'],
                    'label' => $mediaRow['label'],
                    'position' => $mediaRow['position'],
                    'disabled' => $mediaRow['disabled'],
                    'media_type' => $mediaRow['media_type']
                );
            }
        }

        return $result;
    }

    private function _getStoreLabel($storeId, $data)
    {
        return array(
            'store_id' => $storeId,
            'label' => $data['title'],
            'use_default_label' => $data['store_title'] === null ? '1' : '0',
        );
    }

    private function _getStorePrice($storeId, $data)
    {
        return array(
            'store_id' => $storeId,
            'price' => $data['price'],
            'price_type' => $data['price_type'],
            'use_default_price' => $data['store_price'] === null ? '1' : '0',
        );
    }

    protected function prepareCustomOptions(array $productIds)
    {
        $result = $this->_initResult($productIds);

        $multiOptionsTypes = array('multiple', 'checkbox', 'radio', 'drop_down');
        $resultPrepareItem = array();

        foreach ($this->storeIdToCode as $storeId => $storeCode) {
            $options = $this->optionColFactory->create();
            /* @var \Magento\Catalog\Model\ResourceModel\Product\Option\Collection $options */
            $options
                ->reset()
                ->addOrder('sort_order')
                ->addTitleToResult($storeId)
                ->addPriceToResult($storeId)
                ->addProductToFilter($productIds)
                ->addValuesToResult($storeId);

            foreach ($options as $option) {
                $productId = $option['product_id'];
                $optionId = $option['option_id'];

                if (!isset($resultPrepareItem[$productId])) {
                    $resultPrepareItem[$productId] = array();
                }

                if (!isset($resultPrepareItem[$productId][$optionId])) {
                    $resultPrepareItem[$productId][$optionId] = array(
                        'id' => $optionId,
                        'type' => $option['type'],
                        'is_require' => $option['is_require'],
                        'sort_order' => $option['sort_order'],
                        'file_extension' => $option['file_extension'],
                        'image_size_x' => $option['image_size_x'],
                        'image_size_y' => $option['image_size_y'],
                        'max_characters' => $option['max_characters'],
                        'sku' => $option['sku'],
                        'titles' => array(),
                        'prices' => array(),
                        'options' => array(),
                    );
                }

                $resultPrepareItem[$productId][$optionId]['titles'][] = $this->_getStoreLabel($storeId, $option);

                if (in_array($option['type'], $multiOptionsTypes)) {
                    foreach ($option->getValues() as $optionValue) {
                        $subOptionId = $optionValue['option_type_id'];

                        if (!isset($resultPrepareItem[$productId][$optionId]['options'][$subOptionId])) {
                            $resultPrepareItem[$productId][$optionId]['options'][$subOptionId] = array(
                                'sub_option_Id' => $subOptionId,
                                'sku' => $optionValue['sku'],
                                'sort_order' => $optionValue['sort_order'],
                                'titles' => array(),
                                'prices' => array(),
                            );
                        }

                        $resultPrepareItem[$productId][$optionId]['options'][$subOptionId]['prices'][] = $this->_getStorePrice($storeId, $optionValue);
                        $resultPrepareItem[$productId][$optionId]['options'][$subOptionId]['titles'][] = $this->_getStoreLabel($storeId, $optionValue);
                    }
                } else {
                    $resultPrepareItem[$productId][$optionId]['prices'][] = $this->_getStorePrice($storeId, $option);
                }
            }
        }

        foreach ($resultPrepareItem as $productId => $productResult) {
            $productOptions = array();
            foreach ($productResult as $productOption) {
                $productArrayOption = $productOption;
                $productArrayOption['options'] = array_values($productOption['options']);
                $productOptions[] = $productArrayOption;
            }

            $result[$productId] = $productOptions;
        }

        return $result;
    }

    private function getConfigurableProductIds(array $productIds)
    {
        return $this->_getEntityCollection(true)
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToFilter('entity_id', array('in' => $productIds))
            ->addAttributeToFilter('type_id', array('eq' => \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE))
            ->getAllIds();
    }

    /**
     * Prepare configurable products data
     *
     * @param array $productIds
     * @return array
     */
    protected function prepareConfigurableProductLinkedIds(array $productIds)
    {
        $result = array();
        $select = $this->connection->select()
            ->from(
                array('cpsl' => $this->resourceModel->getTableName('catalog_product_super_link')),
                array('cpsl.parent_id', 'cpe.sku', 'cpsl.product_id')
            )
            ->joinLeft(
                array('cpe' => $this->resourceModel->getTableName('catalog_product_entity')),
                '(cpe.entity_id = cpsl.product_id)',
                array()
            )
            ->where('parent_id IN (?)', $productIds);
        $stmt = $this->connection->query($select);
        while ($row = $stmt->fetch()) {
            $result[$row['parent_id']][$row['product_id']] = $row['sku'];
        }

        return $result;
    }

    protected function prepareConfigurableProductAttributes($productIds)
    {
        $result = array();
        $select = $this->connection->select()
            ->from(
                array('attr' => $this->resourceModel->getTableName('catalog_product_super_attribute')),
                array('attr.product_id', 'attr.attribute_id', 'attr.product_super_attribute_id', 'attr.position')
            )
            ->join(
                array('ea' => $this->resourceModel->getTableName('eav_attribute')),
                '(ea.attribute_id = attr.attribute_id)',
                array('ea.attribute_code', 'ea.frontend_label')
            )
            ->where('attr.product_id IN (?)', $productIds)
            ->order('attr.position');
        $stmt = $this->connection->query($select);
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            $result[$productId][$row['attribute_id']] = array(
                'product_super_attribute_id' => $row['product_super_attribute_id'],
                'attribute_code' => $row['attribute_code'],
                'frontend_label' => $row['frontend_label']);
        }
        return $result;
    }

    protected function prepareConfigurableProductLabels($productIds)
    {
        $result = array();
        $select = $this->connection->select()
            ->from(
                array('attr' => $this->resourceModel->getTableName('catalog_product_super_attribute')),
                array('attr.product_id', 'attr.attribute_id', 'label.value', 'label.store_id')
            )
            ->join(
                array('label' => $this->resourceModel->getTableName('catalog_product_super_attribute_label')),
                '(label.product_super_attribute_id = attr.product_super_attribute_id)',
                array('label.use_default')
            )
            ->where('attr.product_id IN (?)', $productIds);
        $stmt = $this->connection->query($select);
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            $result[$productId][$row['attribute_id']][] = array(
                'storeId' => $row['store_id'],
                'label' => $row['value'],
                'use_default' => $row['use_default']);
        }
        return $result;
    }

    public function filterChangedProducts($filterProducts)
    {
        $result = $filterProducts;
        $collection = $this->cobbyProduct->create()
            ->addFieldToFilter('entity_id', array('in' => array_keys($result)));

        foreach ($collection as $item) {
            $productId = $item->getEntityId();

            if (isset($result[$productId]) && $item->getHash() == $result[$productId]) {
                unset($result[$productId]);
            } else {
                $result[$productId] = $item->getHash();
            }
        }
        return $result;
    }

    protected function prepareBundleOptions(array $productIds)
    {
        $result = $this->_initResult($productIds);

        $resource = $this->resourceModel;

        $linkField = $this->getProductEntityLinkField();

        $selectOptions = $this->connection->select()
            ->from(['e' => $resource->getTableName('catalog_product_entity')], ['product_id' => 'e.entity_id'])
            ->join(['o' => $resource->getTableName('catalog_product_bundle_option')], '(o.parent_id = e.' . $linkField . ')')
            ->join(['v' => $resource->getTableName('catalog_product_bundle_option_value')], '(o.option_id = v.option_id)')
            ->where('e.entity_id IN (?)', $productIds);

        $bundleOptions = array();
        $queryOptions = $this->connection->query($selectOptions);
        while ($row = $queryOptions->fetch()) {
            $optionId = $row['option_id'];
            $productId = $row['product_id'];

            if (!isset($bundleOptions[$productId][$optionId])) {
                $bundleOptions[$productId][$optionId] = array(
                    'option_id' => $optionId,
                    'required' => $row['required'],
                    'position' => $row['position'],
                    'type' => $row['type'],
                    'titles' => array(),
                    'selections' => array(),
                );
            }
            $bundleOptions[$productId][$optionId]['titles'][] = array(
                'store_id' => $row['store_id'],
                'title' => $row['title']
            );
        }

        $selectSelections = $this->connection->select()
            ->from(array('s' => $resource->getTableName('catalog_product_bundle_selection')))
            ->joinLeft(array('p' => $resource->getTableName('catalog_product_bundle_selection_price')), '(s.selection_id = p.selection_id)',
                array('website_id' => 'website_id', 'website_price_type' => 'selection_price_type', 'website_price_value' => 'selection_price_value'))
            ->where('s.parent_product_id IN (?)', $productIds);

        $querySelections = $this->connection->query($selectSelections);
        while ($row = $querySelections->fetch()) {
            $optionId = $row['option_id'];
            $productId = $row['parent_product_id'];
            $selectionId = $row['selection_id'];

            if (!isset($bundleOptions[$productId][$optionId][$selectionId])) {
                $bundleOptions[$productId][$optionId]['selections'][$selectionId] = array(
                    'selection_id' => $selectionId,
                    'assigned_product_id' => $row['product_id'],
                    'position' => $row['position'],
                    'is_default' => $row['is_default'],
                    'qty' => $row['selection_qty'],
                    'can_change_qty' => $row['selection_can_change_qty'],
                    'prices' => array(array(
                        'website_id' => 0,
                        'price_type' => $row['selection_price_type'],
                        'price_value' => $row['selection_price_value']))

                );
            }

            if (isset($row['website_id'])) {
                $bundleOptions[$productId][$optionId]['selections'][$selectionId]['prices'][] = array(
                    'website_id' => $row['website_id'],
                    'price_type' => $row['website_price_type'],
                    'price_value' => $row['website_price_value']);
            }
        }

        foreach ($bundleOptions as $productId => $bundleOption) {
            $productOptions = array();
            foreach ($bundleOption as $productOption) {
                $bundleSelections = $productOption;
                $bundleSelections['selections'] = array_values($productOption['selections']);
                $productOptions[] = $bundleSelections;
            }

            $result[$productId] = array_values($productOptions);
        }

        return $result;
    }

    public function export($jsonData)
    {
        $filterProductParams = $this->jsonHelper->jsonDecode($jsonData);
        $result = array();

        $filterChangedProducts = $this->filterChangedProducts($filterProductParams);

        $unchangedProducts = array_diff_key($filterProductParams, $filterChangedProducts);

        foreach ($unchangedProducts as $productId => $hash) {
            $result[$productId][self::COL_MAGENTO_ID] = $productId;
            $result[$productId][self::COL_HASH] = 'UNCHANGED';
        }

        $collection = $this->_getEntityCollection(true)
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToFilter('entity_id', array('in' => array_keys($filterChangedProducts)))
            ->addCategoryIds()
            ->addWebsiteNamesToResult();

        foreach ($collection as $itemId => $item) {

            $result[$itemId] = array(
                self::COL_SKU => $item->getSku(),
                self::COL_MAGENTO_ID => $itemId,
                self::COL_HASH => $filterChangedProducts[$itemId],
                self::COL_ATTR_SET => $item->getAttributeSetId(),
                self::COL_TYPE => $item->getTypeId(),
                self::COL_CATEGORY => implode(",", $item->getCategoryIds()),
                self::COL_WEBSITE => implode(",", $item->getWebsites()),
                self::COL_INVENTORY => null,
                self::COL_GROUP_PRICE => array(),
                self::COL_TIER_PRICE => array(),
                self::COL_LINKS => array(),
                self::COL_IMAGE_GALLERY => array(),
                self::COL_ATTRIBUTES => array(),
                self::COL_CUSTOM_OPTIONS => array(),
                self::COL_BUNDLE_OPTIONS => array(),
            );
        }

        $collection->clear();

        $productIds = array_keys($filterChangedProducts);
        $productAttributes = $this->_prepareAttributes($productIds);
        $productInventory = $this->prepareCatalogInventory($productIds);
        $productLinks = $this->prepareLinks($productIds);
        $productTierPrice = $this->prepareTierPrices($productIds);
        $productImages = $this->prepareMediaGallery($productIds, array_keys($this->storeIdToCode));
        $productCustomOptions = $this->prepareCustomOptions($productIds);
        $productBundleOptions = $this->prepareBundleOptions($productIds);


        foreach ($productIds as $productId) {
            $result[$productId][self::COL_ATTRIBUTES] = $productAttributes[$productId];
            $result[$productId][self::COL_INVENTORY] = $productInventory[$productId];
            $result[$productId][self::COL_LINKS] = $productLinks[$productId];
            $result[$productId][self::COL_TIER_PRICE] = $productTierPrice[$productId];
            $result[$productId][self::COL_IMAGE_GALLERY] = $productImages[$productId];
            $result[$productId][self::COL_CUSTOM_OPTIONS] = $productCustomOptions[$productId];
            $result[$productId][self::COL_BUNDLE_OPTIONS] = $productBundleOptions[$productId];
        }

        $configurableProductIds = $this->getConfigurableProductIds($productIds);
        if (count($configurableProductIds)) {
            $configurableAttributes = $this->prepareConfigurableProductAttributes($configurableProductIds);
            $configurableLabels = $this->prepareConfigurableProductLabels($configurableProductIds);

            foreach ($configurableProductIds as $productId) {
                $configurableData = array();
                if (isset($configurableAttributes[$productId])) {
                    foreach ($configurableAttributes[$productId] as $attributeId => $configurableAttribute) {
                        $configurableData[$attributeId] = array(
                            'attribute_code' => $configurableAttribute['attribute_code'],
                            'attribute_id' => $attributeId,
                            'labels' => isset($configurableLabels[$productId][$attributeId]) ? $configurableLabels[$productId][$attributeId] : array(),
                            'options' => array());
                    }
                }
                $result[$productId][self::COL_SUPER_PRODUCT_ATTRIBUTES] = array_values($configurableData);
            }

            $configurableLinkedIds = $this->prepareConfigurableProductLinkedIds($configurableProductIds);
            foreach ($configurableLinkedIds as $productId => $value) {
                $result[$productId][self::COL_SUPER_PRODUCT_SKUS] = $value;
            }
        }

        if (count($result)) {
            $transportObject = new \Magento\Framework\DataObject();
            $transportObject->setData($result);

            $this->eventManager->dispatch('cobby_catalog_product_export_after',
                array('transport' => $transportObject));

            $result = $transportObject->getData();
        }
        
        return $result;
    }

}
