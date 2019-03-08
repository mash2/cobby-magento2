<?php
/**
 */

namespace Mash2\Cobby\Helper;

use Mash2\Cobby\Helper\Settings;
use Magento\Framework\App\Helper\Context;
use Magento\Backend\Model\UrlInterface;

class SystemCheckHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CODE = 'code';
    const VALUE = 'value';
    const LINK = 'link';
    const OK = 'ok';
    const ERROR = 'error';
    const EXCEPTION = 'exception';
    const VERSION_TO_LOW = 'Your php version has to be bigger than: ';
    const MEMORY_TO_LOW = 'Your memory has to be more then: ';
    const API_ROUTE = 'index.php/rest/V1/integration/admin/token';
    const PHP_MIN_VERSION = '7.0';
    const MIN_MEMORY = 512;
    const NO_DATA = 'It seems like you have no login data, enter your credentials and hit "Save Config"';
    const LOGIN_FAILED = 'It seems like your login data is incorrect, check your credentials';
    const LOGIN_SUCCEED = 'Login data is set up correctly';

    private $settings;
    private $backendUrl;
    private $phpVersion;
    private $memory;
    private $credentials;

    public function __construct(
        Context $context,
        Settings $settings,
        UrlInterface $backendUrl
    )
    {
        parent::__construct($context);

        $this->settings = $settings;
        $this->backendUrl = $backendUrl;
        $this->_init();
    }


    private function _init()
    {
        $this->checkPhpVersion();
        $this->checkMemory();
        $this->checkCredentials();
    }

    private function checkPhpVersion()
    {
        try {
            $version = phpversion();

            if (version_compare($version, self::PHP_MIN_VERSION, '>=')) {
                $code = self::OK;
                $value = $version;
                $link = '';
            } else {
                $code = self::ERROR;
                $value = self::VERSION_TO_LOW . $version;
                $link = 'https://help.cobby.io';
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = 'https://help.cobby.io';
        }

        $this->phpVersion = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkMemory()
    {
        try {
            $memory = ini_get('memory_limit');

            if ((int)$memory >= self::MIN_MEMORY) {
                $code = self::OK;
                $value = $memory;
                $link = '';
            } else {
                $code = self::ERROR;
                $value = self::MEMORY_TO_LOW . $memory;
                $link = 'https://help.cobby.io';
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = 'https://help.cobby.io';
        }

        $this->memory = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function checkCredentials()
    {
        $url = $this->getApiUrl();
        $data = $this->_getLoginData();

        if ($data) {
            $login = $this->_login($url, $data);
            if ($login) {
                $code = self::OK;
                $value = self::LOGIN_SUCCEED;
                $link = '';
            } else {
                $code = self::ERROR;
                $value = self::LOGIN_FAILED;
                $link = 'https://help.cobby.io';
            }
        } else {
            $code = self::EXCEPTION;
            $value = self::NO_DATA;
            $link = 'https://help.cobby.io';
        }

        $this->credentials = array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
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
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $token = json_decode(substr($response, $header_size));

                curl_close($ch);

                if ($http_code !== 200) {
                    Mage::throwException();
                }

                if ($token) {

                    return true;
                }

                return false;
            } catch (Exception $e) {

                return false;
            }
        }

        return false;
    }

}