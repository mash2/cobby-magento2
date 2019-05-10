<?php

namespace Mash2\Cobby\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\MaintenanceMode;
use Magento\Framework\HTTP\Client\Curl;
use Mash2\Cobby\Model\IndexerRepository;

class Systemcheck extends \Magento\Framework\App\Helper\AbstractHelper
{
    const OK = 0;
    const ERROR = 1;
    const EXCEPTION = -1;
    const VALUE = 'value';
    const CODE = 'code';
    const LINK = 'link';
    const URL = 'https://help.cobby.io';
    const API_ROUTE = 'index.php/rest/V1/integration/admin/token';
    const PHP_MIN_VERSION = '7.0';
    const MIN_MEMORY = 512;

    private $relevantIndexers = array(
        'catalog_category_product' => '',
        'catalog_product_price' => '',
        'cataloginventory_stock' => '',
        'catalog_product_flat' => '',
        'catalog_category_flat' => ''
    );

    private $settings;
    private $backendUrl;
    private $phpVersion;
    private $memory;
    private $credentials;
    private $maintenance;
    private $maintenanceMode;
    private $indexers;
    private $url;

    /**
     * @var IndexerRepository
     */
    private $indexerRepository;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $_curl;

    /**
     * Systemcheck constructor.
     * @param Context $context
     * @param Settings $settings
     * @param UrlInterface $backendUrl
     * @param MaintenanceMode $maintenanceMode
     * @param IndexerRepository $indexerRepository
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        Settings $settings,
        UrlInterface $backendUrl,
        MaintenanceMode $maintenanceMode,
        IndexerRepository $indexerRepository,
        Curl $curl
    )
    {
        parent::__construct($context);

        $this->settings = $settings;
        $this->backendUrl = $backendUrl;
        $this->_curl = $curl;
        $this->maintenanceMode = $maintenanceMode;
        $this->indexerRepository = $indexerRepository;
        $this->_init();
    }


    private function _init()
    {
        $this->checkPhpVersion();
        $this->checkMemory();
        $this->checkCredentials();
        $this->checkMaintenanceMode();
        $this->checkIndexers();
        $this->checkUrl();

    }

    private function checkPhpVersion()
    {
        $code = self::OK;
        $value = __('Your php version is ok');
        $link = '';
        try {
            $version = phpversion();

            if (version_compare($version, self::PHP_MIN_VERSION, '<')) {
                $code = self::ERROR;
                $value = __('Your php version is %1, it must be at least %2', $version, self::PHP_MIN_VERSION);
                $link = self::URL;
            }
        } catch (\Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = self::URL;
        }

        $this->phpVersion = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkMemory()
    {
        $code = self::OK;
        $value = __('You have enough memory');
        $link = '';
        try {
            $memory = ini_get('memory_limit');

            if ((int)$memory < self::MIN_MEMORY) {
                $code = self::ERROR;
                $value = __('Your memory is %1MB, it has to be at least %2MB', $memory, self::MIN_MEMORY);
                $link = self::URL;
            }
        } catch (\Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = self::URL;
        }

        $this->memory = array(self::VALUE => $value, self::CODE=> $code, self::LINK => $link);
    }

    private function checkCredentials()
    {
        $code = self::OK;
        $value = __('Login data is set up correctly');
        $link = '';

        $url = $this->getApiUrl();
        $data = $this->_getLoginData();

        if ($data) {
            $login = $this->_login($url, $data);
            if (!$login) {
                $code = self::ERROR;
                $value = __('It seems like your login data is incorrect, check your credentials');
                $link = self::URL;
            }
        } else {
            $code = self::EXCEPTION;
            $value = __('It seems like you have no login data, enter your credentials and hit "Save Config"');
            $link = self::URL;
        }

        $this->credentials = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkMaintenanceMode()
    {
        $isOn = $this->maintenanceMode->isOn();

        if ($isOn) {
            $code = self::ERROR;
            $value = __('Maintenance mode is active');
            $link = 'https://help.cobby.io';
        } else {
            $code = self::OK;
            $value = __('Maintenance mode is not active');
            $link = '';
        }

        $this->maintenance = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkIndexers()
    {
        $value = __('No index is running');
        $code = self::OK;
        $link = '';

        $runningIndexers = array();

        $indexers = $this->indexerRepository->export();

        foreach ($indexers as $indexer) {
            if (key_exists($indexer['code'], $this->relevantIndexers) && $indexer['status'] == 'working') {
                $runningIndexers[] = $indexer['title'];
            }
        }

        if (!empty($runningIndexers)) {
            $value = __('Magento indexers are running. Indexers: ') .implode('; ', $runningIndexers);
            $code = self::ERROR;
            $link = self::URL;
        }

        $this->indexers = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkUrl()
    {
        $value = 'Your url is up to date';
        $code = self::OK;
        $link = '';

        $baseUrl = $this->settings->getDefaultBaseUrl();
        $cobbyUrl = $this->settings->getCobbyUrl();

        $len = strlen($cobbyUrl);

        if (substr($baseUrl, 0, $len) !== $cobbyUrl) {
            $value = 'Your cobby url does not match the shop url, you need to save config or disable cobby';
            $code = self::ERROR;
            $link = self::URL;
        }

        $this->url = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function getElement($section)
    {
        return $this->{$section};
    }

    private function getApiUrl()
    {
        $baseUrl = $this->backendUrl->turnOffSecretKey()->getUrl('adminhtml');

        $url = explode(':', $baseUrl);

        return $url[0] . ':' . $url[1] . '/' . self::API_ROUTE;
    }

    private function _getLoginData()
    {
        $apiUserName = $this->settings->getApiUser();
        $apiKey = $this->settings->getApiPassword();

        if ($apiUserName && $apiKey) {
            $data = array(
                "username" => $apiUserName,
                "password" => $apiKey
            );

            return json_encode($data);
        }

        return false;

    }

    private function _login($url, $data)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {
                $this->_curl->setHeaders(array('Content-Type: application/json'));
                $this->_curl->post($url, $data);

                $http_code = $this->_curl->getStatus();
                $token = $this->_curl->getBody();

                if ($http_code !== 200) {
                    return false;
                }

                if ($token) {

                    return true;
                }

                return false;
            } catch (\Exception $e) {

                return false;
            }
        }

        return false;
    }

}