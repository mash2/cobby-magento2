<?php

namespace Mash2\Cobby\Block\System\Config;

use Magento\Backend\Block\Context;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\LayoutFactory;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use mysql_xdevapi\Exception;
use Magento\Backend\Model\UrlInterface;
use Mash2\Cobby\Helper\Settings;

/**
 * Class Troubleshooter
 * @package Mash2\Cobby\Block\System\Config
 */
class Troubleshooter extends Fieldset
{
    const PHP_MIN_VERSION = "7.0";
    const VERSION_TO_LOW = 'Your php version has to be bigger than: ';
    const MEMORY_TO_LOW = 'Your memory has to be more then: ';
    const API_ROUTE = 'index.php/rest';

    /**
     * @var \Magento\Framework\View\LayoutFactory
     */
    private $_layoutFactory;

    private $backendUrl;

    private $settings;

    /**
     * @param Context $context
     * @param Js $jsHelper
     * @param Session $authSession
     * @param LayoutFactory $layoutFactory
     * @param UrlInterface $backendUrl
     * @param Settings $settings
     * @param array $data
     */
    public function __construct(
        Context $context,
        Js $jsHelper,
        Session $authSession,
        LayoutFactory $layoutFactory,
        UrlInterface $backendUrl,
        Settings $settings,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);

        $this->_layoutFactory = $layoutFactory;
        $this->backendUrl = $backendUrl;
        $this->settings = $settings;
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = $this->_getHeaderHtml($element);

        $html .= $this->getPhpVersion($element);
        $html .= $this->getMemory($element);
        //$html .= $this->checkCredentials($element);

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    private function getPhpVersion($fieldset)
    {
        $label = __("Php Version");
        $version = '';
        $error = false;

        try {
            $version = phpversion();
        } catch (Exception $e) {
            $error = true;
            $errorMsg = $e->getMessage();
        }

        if ($error) {
            $value = '<div class="yellow">';
            $value .= $errorMsg . "</div>";
        } else if (version_compare($version, self::PHP_MIN_VERSION, '>=')) {
            $value = '<div class="green">';
            $value .= $version . __(' OK') . "</div>";
        } else {
            $value = '<div class="red">';
            $value .= __(self::VERSION_TO_LOW) . $version;
            $value .=
                "<a target='_blank'
                  href='https://help.cobby.io'>" .
                __("Get help") .
                "</a>" . "</div>";
        }

        return $this->getFieldHtml($fieldset, 'phpversion', $label, $value);
    }

    private function getMemory($fieldset)
    {
        $label = __("Memory");
        $error = false;


        try {
            $memory = ini_get('memory_limit');
        } catch (Exception $e) {
            $error = true;
            $errorMsg = $e->getMessage();
        }

        if ($error) {
            $value = '<div class="yellow">';
            $value .= $errorMsg . "</div>";
        } else if ((int)$memory >= 512) {
            $value = '<div class="green">';
            $value .= $memory . __(' OK') . "</div>";
        } else {
            $value = '<div class="red">';
            $value .= __(self::MEMORY_TO_LOW) . $memory;
            $value .=
                "<a target='_blank'
                  href='https://help.cobby.io'>" .
                __("Get help") .
                "</a>" . "</div>";
        }

        return $this->getFieldHtml($fieldset, 'memory', $label, $value);
    }

    private function htmlBuilder($value, $code, $hint)
    {
        $result = '';
        switch ($code) {
            case self::ERROR:
                $value = '<div class="yellow">';
                $value .= $value . "</div>";
                break;
            case self::EXCEPTION:
                $value = '<div class="red">';
                $value .= __(self::MEMORY_TO_LOW) . $value;
                $value .=
                    "<a target='_blank'
                  href='https://help.cobby.io'>" .
                    __("Get help") .
                    "</a>" . "</div>";
                break;
            case self::OK:
                $value = '<div class="green">';
                $value .= $value . __(' OK') . "</div>";
                break;
        }

        return $result;
    }

    private function checkCredentials($fieldset)
    {
        $url = $this->getApiUrl();
        $data = $this->_getLoginData();
        $login = $this->_login($url, $data);

        return $login;
    }

    private function getApiUrl()
    {
        $baseUrl = $this->backendUrl->turnOffSecretKey()->getUrl('adminhtml');

        $url = explode(':', $baseUrl);

        return $url[0] . ':' . $url[1] . '/' . self::API_ROUTE;
    }

    protected function _getLoginData()
    {
        $apiUserName = $this->settings->getApiUser();
        $apiKey = $this->settings->getApiPassword();

        $data = array(
            "method" => "login",
            "params" => array($apiUserName, $apiKey),
            "id" => "id"
        );

        return json_encode($data);
    }

    protected function _login($url, $data)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                //$header = substr($response, 0, $header_size);
                $body = json_decode(substr($response, $header_size));

                $token = $body->result;
                curl_close($ch);

                if ($http_code !== 200) {
                    Mage::throwException("Http code: " .$http_code);
                }

                if ($token) {
                    return true;
                }

                $errorMsg = $body->error->message;
                $error = array('401' => $errorMsg);
                return $error;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $error = array('400' => $msg);

                return  $error;
            }
        }

        return 'Not a valid url';
    }


    private function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $layout = $this->_layoutFactory->create();

            $this->_fieldRenderer = $layout->createBlock(
                \Magento\Config\Block\System\Config\Form\Field::class
            );
        }

        return $this->_fieldRenderer;
    }

    /**
     * @param AbstractElement $fieldset
     * @param string $fieldName
     * @param string $label
     * @param string $value
     * @return string
     */
    private function getFieldHtml($fieldset, $fieldName, $label = '', $value = '')
    {
        $field = $fieldset->addField($fieldName, 'label', [
            'name'  => 'dummy',
            'label' => $label,
            'after_element_html' => $value,
        ])->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}