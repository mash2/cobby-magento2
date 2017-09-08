<?php
namespace Mash2\Cobby\Model;

class Import implements \Mash2\Cobby\Api\ImportInterface
{
    const START = 'start';
    const FINISH = 'finish';

    /**
     * Json Helper
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Mash2\Cobby\Api\ImportProductLinkManagementInterface
     */
    private $importProductLink;

    /**
     * @var \Mash2\Cobby\Api\ImportProductCategoryManagementInterface
     */
    private $importProductCategory;

    /**
     * @var \Mash2\Cobby\Api\ImportProductTierPriceManagementInterface
     */
    private $importProductTierPrice;

    /**
     * @var \Mash2\Cobby\Api\ImportProductManagementInterface
     */
    private $importProduct;

    /**
     * @var \Mash2\Cobby\Api\ImportProductStockManagementInterface
     */
    private $importProductStock;

    /**
     * @var \Mash2\Cobby\Api\ImportProductImageManagementInterface
     */
    private $importProductImage;

    /**
     * @var \Mash2\Cobby\Api\ImportProductGroupedManagementInterface
     */
    private $importProductGrouped;

    /**
     * @var \Mash2\Cobby\Api\ImportProductConfigurableManagementInterface
     */
    private $importProductConfigurable;
    
    /**
     * @var \Mash2\Cobby\Api\ImportProductUrlManagementInterface
     */
    private $importProductUrl;

    /**
     * @var \Magento\ImportExport\Model\ImportFactory
     */
    private $importModelFactory;

    /**
     * @var \Mash2\Cobby\Api\ImportProductBundleManagementInterface
     */
    private $importProductBundle;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Mash2\Cobby\Api\ImportProductLinkManagementInterface $importProductLink
     * @param \Mash2\Cobby\Api\ImportProductCategoryManagementInterface $importProductCategory
     * @param \Mash2\Cobby\Api\ImportProductTierPriceManagementInterface $importProductTierPrice
     * @param \Mash2\Cobby\Api\ImportProductManagementInterface $importProduct
     * @param \Mash2\Cobby\Api\ImportProductStockManagementInterface $importProductStock
     * @param \Mash2\Cobby\Api\ImportProductImageManagementInterface $importProductImage
     * @param \Mash2\Cobby\Api\ImportProductGroupedManagementInterface $importProductGrouped
     * @param \Mash2\Cobby\Api\ImportProductConfigurableManagementInterface $importProductConfigurable
     * @param \Mash2\Cobby\Api\ImportProductUrlManagementInterface $importProductUrl
     * @param \Magento\ImportExport\Model\ImportFactory $importModelFactory
     * @param \Mash2\Cobby\Api\ImportProductBundleManagementInterface $importProductBundle
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Mash2\Cobby\Api\ImportProductLinkManagementInterface $importProductLink,
        \Mash2\Cobby\Api\ImportProductCategoryManagementInterface $importProductCategory,
        \Mash2\Cobby\Api\ImportProductTierPriceManagementInterface $importProductTierPrice,
        \Mash2\Cobby\Api\ImportProductManagementInterface $importProduct,
        \Mash2\Cobby\Api\ImportProductStockManagementInterface $importProductStock,
        \Mash2\Cobby\Api\ImportProductImageManagementInterface $importProductImage,
        \Mash2\Cobby\Api\ImportProductGroupedManagementInterface $importProductGrouped,
        \Mash2\Cobby\Api\ImportProductConfigurableManagementInterface $importProductConfigurable,
        \Mash2\Cobby\Api\ImportProductUrlManagementInterface $importProductUrl,
        \Magento\ImportExport\Model\ImportFactory $importModelFactory,
        \Mash2\Cobby\Api\ImportProductBundleManagementInterface $importProductBundle,
        \Magento\Framework\Event\ManagerInterface $eventManager

    ) {
        $this->jsonHelper = $jsonHelper;
        $this->importProductLink = $importProductLink;
        $this->importProductCategory = $importProductCategory;
        $this->importProductTierPrice = $importProductTierPrice;
        $this->importProduct = $importProduct;
        $this->importProductStock = $importProductStock;
        $this->importProductImage = $importProductImage;
        $this->importProductGrouped = $importProductGrouped;
        $this->importProductConfigurable = $importProductConfigurable;
        $this->importProductUrl = $importProductUrl;
        $this->importModelFactory = $importModelFactory;
        $this->importProductBundle = $importProductBundle;
        $this->eventManager = $eventManager;
    }

    /**
     * @inheritdoc
     */
    public function importProducts($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProduct->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductLinks($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductLink->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductCategories($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductCategory->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductTierPrices($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductTierPrice->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductStocks($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductStock->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductImages($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductImage->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductGrouped($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductGrouped->import($rows);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function importProductConfigurable($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductConfigurable->import($rows);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function importProductUrls($jsonData)
    {
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductUrl->import($rows);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function importProductBundle($jsonData){
        $rows = $this->jsonHelper->jsonDecode($jsonData);
        $result = $this->importProductBundle->import($rows);
        return $result;
    }

    public function importProductsStart()
    {
        $this->eventManager->dispatch('cobby_import_product_started');

        return true;
    }

    /**
     * @param \Mash2\Cobby\Api\Data\ImportProductsFinishInterface $data
     * @return bool
     */
    public function importProductsFinish(\Mash2\Cobby\Api\Data\ImportProductsFinishInterface $data)
    {
        $this->eventManager->dispatch('cobby_import_product_finished', array(
                'entities'          => $data->getEntities()));

        return true;
    }
}
