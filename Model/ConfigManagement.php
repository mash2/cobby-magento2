<?php
namespace Mash2\Cobby\Model;


class ConfigManagement implements \Mash2\Cobby\Api\ConfigManagementInterface
{
    /**
     * config paths use in cobby
     *
     * @var array
     */
    protected $_configPaths = [
        'cobby/settings/overwrite_images',
        'cobby/settings/cobby_version',
        'cobby/settings/clear_cache',
        'web/unsecure/base_media_url',
		'cataloginventory/item_options/manage_stock',
        'cataloginventory/item_options/backorders',
        'cataloginventory/item_options/min_qty',
        \Mash2\Cobby\Helper\Settings::XML_PATH_COBBY_SETTINGS_MANAGE_STOCK
    ];

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $backendUrl;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    private $productMetadata;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Magento\Framework\App\ProductMetadata $productMetadata
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->backendUrl = $backendUrl;
        $this->productMetadata = $productMetadata;
    }

    public function getList()
    {
        $result = array();
        $isEE = $this->productMetadata->getEdition() != \Magento\Framework\App\ProductMetadata::EDITION_NAME;
        $magentoVersion = $this->productMetadata->getVersion();
        $adminUrl = $this->backendUrl->turnOffSecretKey()->getUrl('adminhtml');

        foreach ($this->storeManager->getStores(true) as $store) {
            $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

            $storeConfigs = array(
                'store_id' => $store->getId(),
                'web/unsecure/base_url' => $baseUrl,
                'cobby/settings/admin_url' => $adminUrl,
                'mage/core/enterprise' => $isEE,
                'mage/core/magento_version' => $magentoVersion
            );

            foreach($this->_configPaths as $path)
            {
                $storeConfigs[$path] = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId());
            }
            $storeConfigs['web/unsecure/base_media_url'] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            
            $result[] = $storeConfigs;
        }

        return $result;
    }
}
