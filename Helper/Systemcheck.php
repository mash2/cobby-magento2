<?php

namespace Mash2\Cobby\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\MaintenanceMode;
use Mash2\Cobby\Model\IndexerRepository;
use Magento\User\Model\User;

class Systemcheck extends \Magento\Framework\App\Helper\AbstractHelper
{
    const OK = 0;
    const ERROR = 1;
    const EXCEPTION = -1;
    const VALUE = 'value';
    const CODE = 'code';
    const LINK = 'link';
    const URL = 'https://help.cobby.io';
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
    private $phpVersion;
    private $memory;
    private $credentials;
    private $maintenance;
    private $maintenanceMode;
    private $indexers;
    private $url;
    private $cobbyActive;
    private $cobbyVersion;


    /**
     * @var IndexerRepository
     */
    private $indexerRepository;

    /**
     * @var User
     */
    protected $user;

    /**
     * Systemcheck constructor.
     * @param Context $context
     * @param Settings $settings
     * @param MaintenanceMode $maintenanceMode
     * @param IndexerRepository $indexerRepository
     * @param User $user
     */
    public function __construct(
        Context $context,
        Settings $settings,
        MaintenanceMode $maintenanceMode,
        IndexerRepository $indexerRepository,
        User $user
    )
    {
        parent::__construct($context);

        $this->settings = $settings;
        $this->user = $user;
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
        $this->checkCobbyActive();
        $this->checkCobbyVersion();

    }

    public function getTestResults()
    {
        $result = array(
            'php_version' => $this->phpVersion,
            'memory' => $this->memory,
            'login' => $this->credentials,
            'maintenance' => $this->maintenance,
            'indexers' => $this->indexers,
            'url' => $this->url,
            'cobby_active' => $this->cobbyActive,
            'cobby_version' => $this->cobbyVersion
        );

        return $result;
    }

    private function checkPhpVersion()
    {
        $code = self::OK;
        $value = __('PHP version ok');
        $link = '';
        try {
            $version = phpversion();

            if (version_compare($version, self::PHP_MIN_VERSION, '<')) {
                $code = self::ERROR;
                $value = __('PHP version is %1, it must be at least %2', $version, self::PHP_MIN_VERSION);
                $link = self::URL;
            }
        } catch (\Exception $e) {
            $code = self::EXCEPTION;
            $value = __('Couldn’t be checked: ') . $e->getMessage();
            $link = self::URL;
        }

        $this->phpVersion = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkMemory()
    {
        $code = self::OK;
        $value = __('Memory ok');
        $link = '';
        try {
            $memory = $this->getMBytes(ini_get('memory_limit'));

            if ($memory < self::MIN_MEMORY) {
                $code = self::ERROR;
                $value = __('Memory is %1MB, it has to be at least %2MB', $memory, self::MIN_MEMORY);
                $link = self::URL;
            }
        } catch (\Exception $e) {
            $code = self::EXCEPTION;
            $value = __('Couldn’t be checked: ') . $e->getMessage();
            $link = self::URL;
        }

        $this->memory = array(self::VALUE => $value, self::CODE=> $code, self::LINK => $link);
    }

    private function getMBytes($val)
    {
        $val = trim($val);
        $valInt = (int)$val;
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $valInt *= 1024;
                break;
            case 'k':
                $valInt /= 1024;
                break;
        }
        return $valInt;
    }

    private function checkCredentials()
    {
        $code = self::OK;
        $value = __('Login data is set up correctly');
        $link = '';

        $data = $this->_getLoginData();

        if ($data) {
            $login = $this->user->login($data['username'], $data['password']);
            if (empty($login->getId())) {
                $code = self::ERROR;
                $value = __('It seems the provided credentials are wrong');
                $link = self::URL;
            }
        } else {
            $code = self::EXCEPTION;
            $value = __('It seems like you have no login data, enter your credentials and save config');
            $link = self::URL;
        }

        $this->credentials = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkMaintenanceMode()
    {
        $code = self::OK;
        $value = __('Is not active');
        $link = '';

        $isOn = $this->maintenanceMode->isOn();

        if ($isOn) {
            $code = self::ERROR;
            $value = __('Is active');
            $link = self::URL;
        }

        $this->maintenance = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkIndexers()
    {
        $value = __('Index is valid');
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
            $value = __('Indexing is in progress for: ') .implode('; ', $runningIndexers);
            $code = self::ERROR;
            $link = self::URL;
        }

        $this->indexers = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkUrl()
    {
        $value = __('URL is up to date');
        $code = self::OK;
        $link = '';

        $baseUrl = $this->settings->getDefaultBaseUrl();
        $cobbyUrl = $this->settings->getCobbyUrl();

        $len = strlen($cobbyUrl);

        if (substr($baseUrl, 0, $len) !== $cobbyUrl && !empty($cobbyUrl)) {
            $value = __("The cobby URL doesn't match the base URL, save config or disable cobby");
            $code = self::ERROR;
            $link = self::URL;
        } else if (empty($cobbyUrl)){
            $value = __("The URL can't be checked, save config");
            $code = self::EXCEPTION;
            $link = self::URL;
        }

        $this->url = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkCobbyActive()
    {
        $value = __('Cobby is active');
        $code = self::OK;
        $link = '';

        $active = $this->scopeConfig->isSetFlag('cobby/settings/active');

        if (!$active) {
            $value = __('Cobby must be activated to work as expected');
            $code = self::ERROR;
            $link = self::URL;
        }

        $this->cobbyActive = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkCobbyVersion()
    {
        $value = __('Your module version is synchronized');
        $code = self::OK;
        $link = '';

        $dbVersion = $this->scopeConfig->getValue('cobby/settings/cobby_dbversion');
        $moduleVersion = $this->settings->getCobbyVersion();

        if ($dbVersion != $moduleVersion) {
            $value = __('Your module version is not synchronized, save config for synchronization');
            $code = self::ERROR;
            $link = self::URL;
        }

        $this->cobbyVersion = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);

    }

    public function getElement($section)
    {
        return $this->{$section};
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

            return $data;
        }

        return false;

    }
}
