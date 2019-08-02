<?php
namespace Mash2\Cobby\Model\Import\Product;

use Braintree\Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as RowValidator;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException as UrlAlreadyExistsException;


class UrlManagement extends AbstractManagement implements \Mash2\Cobby\Api\ImportProductUrlManagementInterface
{

    /**
     * Url key attribute code
     */
    const URL_KEY = 'url_key';

    /**
     * Column product store.
     */
    const COL_STORE_ID = 'store_id';

    /**
     * Column product id.
     */
    const COL_PRODUCT_ID = 'product_id';

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

    /** @var UrlPersistInterface */
    protected $urlPersist;

    /** @var array */
    protected $products = [];

    /** @var \Magento\Catalog\Model\ProductFactory $catalogProductFactory */
    protected $catalogProductFactory;

    /** @var UrlFinderInterface */
    protected $urlFinder;

    /** @var array */
    protected $storesCache = [];

    /** @var array */
    protected $categoryCache = [];

    /** @var array */
    protected $websitesToStoreIds;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor
     */
    protected $categoryProcessor;

    /** @var \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator */
    protected $productUrlPathGenerator;

    /** @var array */
    protected $vitalForGenerationFields = [
        'sku',
        'url_key',
        'url_path',
        'name',
        'visibility',
    ];

    /** @var UrlRewriteFactory */
    protected $urlRewriteFactory;

    protected $newUrls;

    /**
     * constructor.
     * @param \Magento\Framework\App\ResourceConnection $resourceModel
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Mash2\Cobby\Helper\Settings $settings
     * @param \Magento\Catalog\Model\Product\Url $productUrl
     * @param UrlFinderInterface $urlFinder
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param UrlPersistInterface $urlPersist
     * @param \Magento\Catalog\Model\ProductFactory $catalogProductFactory
     * @param UrlRewriteFactory $urlRewriteFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor
     * @param \Mash2\Cobby\Model\Product $product
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Catalog\Model\Product\Url $productUrl,
        UrlFinderInterface $urlFinder,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        UrlPersistInterface $urlPersist,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        UrlRewriteFactory $urlRewriteFactory,
        \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
        \Mash2\Cobby\Model\Product $product
    ) {
        parent::__construct($resourceModel, $productCollectionFactory, $eventManager, $resourceHelper, $product);
        $this->settings = $settings;
        $this->productUrl = $productUrl;
        $this->urlKeyAttribute = $resourceFactory->create()->getAttribute('url_key');
        $this->storeManager = $storeManager;
        $this->urlPersist = $urlPersist;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->urlFinder = $urlFinder;
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->productUrlPathGenerator = $productUrlPathGenerator;
        $this->categoryProcessor = $categoryProcessor;

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

        $productIds = array_column($rows, 'product_id');
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();
        $this->eventManager->dispatch('cobby_import_product_url_import_before', array( 'products' => $productIds ));

        $attributesData = array();
        foreach($rows as $rowData) {
            $productId = $rowData[self::COL_PRODUCT_ID];
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $storeValues = $rowData['values'];
            $attributesData[$productId] = $this->prepareUrlAttributes($productId, $storeValues);
            foreach($storeValues as $storeValue) {
                $this->_populateForUrlGeneration($productId, $rowData['website_ids'], $rowData['category_ids'], $storeValue);
            }
            $changedProductIds[] = $productId;
        }

        $this->saveProductAttributes($attributesData);
        $this->touchProducts($changedProductIds);

        $productUrls = $this->generateUrls();

        foreach ($changedProductIds as $productId) {
            $filteredProductUrls = array_filter($productUrls, function($k) use($productId){
                return $k->getEntityId() == $productId;
            });

            if ($filteredProductUrls) {
                $defaultStoreUrls = array_filter($filteredProductUrls, function($k){
                    return $k->getMetadata() == null;
                });

                $urls = array();
                $error_code = null;

                foreach ($defaultStoreUrls as $storeUrl){
                    $urls[] = array(
                        "store_id" => $storeUrl->getStoreId(),

                        "url_key" => $storeUrl->getRequestPath());
                }
                try {
                    $this->urlPersist->replace($filteredProductUrls);
                } catch (UrlAlreadyExistsException $e) {
                    $error_code = RowValidator::ERROR_DUPLICATE_URL_KEY;
                }

                $result[] = array("product_id" => $productId, "urls" => $urls, "error_code" => $error_code);
            }
        }

        $this->eventManager->dispatch('cobby_import_product_url_import_after', array( 'products' => $changedProductIds ));

        return $result;
    }

    /**
     * Create product model from imported data for URL rewrite purposes.
     *
     * @param $productId
     * @param $websiteIds
     * @param $categoryIds
     * @param array $rowData
     *
     * @return UrlManagement
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _populateForUrlGeneration($productId, $websiteIds, $categoryIds, $rowData)
    {
        $rowData['entity_id'] = $productId;

        $product = $this->catalogProductFactory->create();
        $product->setId($rowData['entity_id']);
        $product->setData('save_rewrites_history', true);

        foreach ($this->vitalForGenerationFields as $field) {
            if (isset($rowData[$field])) {
                $product->setData($field, $rowData[$field]);
            }
        }

        $this->categoryCache[$productId] = $categoryIds;
        foreach ($websiteIds as $websiteId) {
            if (!isset($this->websitesToStoreIds[$websiteId])) {
                $this->websitesToStoreIds[$websiteId] = $this->storeManager->getWebsite($websiteId)->getStoreIds();
            }
        }

        $storeId = $rowData[self::COL_STORE_ID];
        $product->setStoreId($storeId);

        if ($this->isGlobalScope($product->getStoreId())) {
            $this->populateGlobalProduct($product, $websiteIds);
        } else {
            $this->addProductToImport($product, $product->getStoreId());
        }

        return $this;
    }

    /**
     * Populate global product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param $productWebsiteIds
     * @return $this
     */
    protected function populateGlobalProduct($product, $productWebsiteIds)
    {
        foreach ($productWebsiteIds as $websiteId) {
            foreach ($this->websitesToStoreIds[$websiteId] as $storeId) {
                $this->storesCache[$storeId] = true;
                if (!$this->isGlobalScope($storeId)) {
                    $this->addProductToImport($product, $storeId);
                }
            }
        }
        return $this;
    }

    /**
     * Check is global scope
     *
     * @param int|null $storeId
     * @return bool
     */
    protected function isGlobalScope($storeId)
    {
        return null === $storeId || $storeId == Store::DEFAULT_STORE_ID;
    }

    /**
     * Generate product url rewrites
     *
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateUrls()
    {
        /**
         * @var $urls \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
         */
        $urls = array_merge(
            $this->canonicalUrlRewriteGenerate(),
            $this->categoriesUrlRewriteGenerate()
           // $this->currentUrlRewritesRegenerate()
        );

        /* Reduce duplicates. Last wins */
        $result = [];
        foreach ($urls as $url) {
            $result[$url->getTargetPath() . '-' . $url->getStoreId()] = $url;
        }
        return $result;
    }

    /**
     * Generate list based on store view
     *
     * @return UrlRewrite[]
     */
    protected function canonicalUrlRewriteGenerate()
    {
        $urls = [];
        foreach ($this->products as $productId => $productsByStores) {
            foreach ($productsByStores as $storeId => $product) {
                if ($this->productUrlPathGenerator->getUrlPath($product)) {
                    $urls[] = $this->urlRewriteFactory->create()
                        ->setEntityType(ProductUrlRewriteGenerator::ENTITY_TYPE)
                        ->setEntityId($productId)
                        ->setRequestPath($this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId))
                        ->setTargetPath($this->productUrlPathGenerator->getCanonicalUrlPath($product))
                        ->setStoreId($storeId);
                }
            }
        }
        $this->newUrls = $urls;

        return $urls;
    }

    /**
     * Generate list based on categories
     *
     * @return UrlRewrite[]
     */
    protected function categoriesUrlRewriteGenerate()
    {
        $urls = [];
        foreach ($this->products as $productId => $productsByStores) {
            foreach ($productsByStores as $storeId => $product) {
                foreach ($this->categoryCache[$productId] as $categoryId) {
                    $category = $this->categoryProcessor->getCategoryById($categoryId);
                    if (!$category || $category->getParentId() == Category::TREE_ROOT_ID) {
                        continue;
                    }
                    $requestPath = $this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId, $category);
                    $urls[] = $this->urlRewriteFactory->create()
                        ->setEntityType(ProductUrlRewriteGenerator::ENTITY_TYPE)
                        ->setEntityId($productId)
                        ->setRequestPath($requestPath)
                        ->setTargetPath($this->productUrlPathGenerator->getCanonicalUrlPath($product, $category))
                        ->setStoreId($storeId)
                        ->setMetadata(['category_id' => $category->getId()]);
                }
            }
        }
//        $this->newUrls[] = $urls;

        return $urls;
    }

    /**
     * Generate list based on current rewrites
     *
     * @return UrlRewrite[]
     */
    protected function currentUrlRewritesRegenerate()
    {
        $currentUrlRewrites = $this->urlFinder->findAllByData(
            [
                UrlRewrite::STORE_ID => array_keys($this->storesCache),
                UrlRewrite::ENTITY_ID => array_keys($this->products),
                UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
            ]
        );


//        $urls = array();
//        foreach ($this->newUrls as $url) {
//            $urls[] = $url->getData('request_path');
//        }

        $urlRewrites = [];
        foreach ($currentUrlRewrites as $currentUrlRewrite) {

                $currentUrlRewrite->setData('target_path', $this->newUrls->getData('request_path'));



//            $category = $this->retrieveCategoryFromMetadata($currentUrlRewrite);
//            if ($category === false) {
//                continue;
//            }
//            $url = $currentUrlRewrite->getIsAutogenerated()
//                ? $this->generateForAutogenerated($currentUrlRewrite, $category)
//                : $this->generateForCustom($currentUrlRewrite, $category);
//            $urlRewrites = array_merge($urlRewrites, $url);
        }

        return $urlRewrites;
    }

    /**
     * @param UrlRewrite $url
     * @param Category $category
     * @return array
     */
    protected function generateForAutogenerated($url, $category)
    {
        $storeId = $url->getStoreId();
        $productId = $url->getEntityId();
        if (isset($this->products[$productId][$storeId])) {
            $product = $this->products[$productId][$storeId];
            if (!$product->getData('save_rewrites_history')) {
                return [];
            }
            $targetPath = $this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId, $category);
            if ($url->getRequestPath() === $targetPath) {
                return [];
            }
            return [
                $this->urlRewriteFactory->create()
                    ->setEntityType(ProductUrlRewriteGenerator::ENTITY_TYPE)
                    ->setEntityId($productId)
                    ->setRequestPath($url->getRequestPath())
                    ->setTargetPath($targetPath)
                    ->setRedirectType(OptionProvider::PERMANENT)
                    ->setStoreId($storeId)
                    ->setDescription($url->getDescription())
                    ->setIsAutogenerated(0)
                    ->setMetadata($url->getMetadata())
            ];
        }
        return [];
    }

    /**
     * @param UrlRewrite $url
     * @param Category $category
     * @return array
     */
    protected function generateForCustom($url, $category)
    {
        $storeId = $url->getStoreId();
        $productId = $url->getEntityId();
        if (isset($this->products[$productId][$storeId])) {
            $product = $this->products[$productId][$storeId];
            $targetPath = $url->getRedirectType()
                ? $this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId, $category)
                : $url->getTargetPath();
            if ($url->getRequestPath() === $targetPath) {
                return [];
            }
            return [
                $this->urlRewriteFactory->create()
                    ->setEntityType(ProductUrlRewriteGenerator::ENTITY_TYPE)
                    ->setEntityId($productId)
                    ->setRequestPath($url->getRequestPath())
                    ->setTargetPath($targetPath)
                    ->setRedirectType($url->getRedirectType())
                    ->setStoreId($storeId)
                    ->setDescription($url->getDescription())
                    ->setIsAutogenerated(0)
                    ->setMetadata($url->getMetadata())
            ];
        }
        return [];
    }

    /**
     * @param UrlRewrite $url
     * @return Category|null|bool
     */
    protected function retrieveCategoryFromMetadata($url)
    {
        $metadata = $url->getMetadata();
        if (isset($metadata['category_id'])) {
            $category = $this->categoryProcessor->getCategoryById($metadata['category_id']);
            return $category === null ? false : $category;
        }
        return null;
    }

    /**
     * Add product to import
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $storeId
     * @return $this
     */
    protected function addProductToImport($product, $storeId)
    {
        if ($product->getVisibility() == (string)Visibility::getOptionArray()[Visibility::VISIBILITY_NOT_VISIBLE]) {
            return $this;
        }
        if (!isset($this->products[$product->getId()])) {
            $this->products[$product->getId()] = [];
        }
        $this->products[$product->getId()][$storeId] = $product;
        return $this;
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
                        $this->getProductEntityLinkField() .'=?' => $linkId,
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

    private function formatUrlKey($productId, $urlKey) {
        if ( !empty($urlKey) && $urlKey != ProductManagement::COBBY_DEFAULT) {
            $urlKey = $this->productUrl->formatUrlKey($urlKey);
        }

        return $urlKey;
    }

}
