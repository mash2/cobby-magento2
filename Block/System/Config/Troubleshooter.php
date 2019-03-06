<?php

namespace Mash2\Cobby\Block\System\Config;

use Magento\Backend\Block\Context;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\LayoutFactory;
use Magento\Backend\Model\UrlInterface;
use Mash2\Cobby\Helper\Settings;

/**
 * Class Troubleshooter
 * @package Mash2\Cobby\Block\System\Config
 */
class Troubleshooter extends Fieldset
{
    const PHP_MIN_VERSION = '7.0';
    const MIN_MEMORY = 512;
    const VERSION_TO_LOW = 'Your php version has to be bigger than: ';
    const MEMORY_TO_LOW = 'Your memory has to be more then: ';
    const API_ROUTE = 'index.php/rest/V1/integration/admin/token';
    const OK = 0;
    const ERROR = 1;
    const EXCEPTION = -1;
    const NO_DATA = 'It seems like you have no login data, enter your credentials and hit "Save Config"';
    const LOGIN_FAILED = 'It seems like your login data is incorrect, check your credentials';
    const LOGIN_SUCCEED = 'Login data is set up correctly';

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
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = $this->_getHeaderHtml($element);

        $html .= $this->getPhpVersion($element);
        $html .= $this->getMemory($element);
        $html .= $this->checkCredentials($element);

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    private function getPhpVersion($fieldset)
    {
        $label = __("Php Version");
        $hint = '';

        try {
            //$value = phpversion();
            $value = '5.6';
            if (version_compare($value, self::PHP_MIN_VERSION, '>=')) {
                $code = self::OK;
            } else {
                $code = self::ERROR;
                $value = self::VERSION_TO_LOW . $value;
                $hint = 'https://help.cobby.io';
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $hint = 'https://help.cobby.io';
        }

        $fieldValue = $this->htmlBuilder($value, $code, $hint);

        return $this->getFieldHtml($fieldset, 'phpversion', $label, $fieldValue);
    }

    private function getMemory($fieldset)
    {
        $label = __("Memory");
        $hint = '';

        try {
            //$value = ini_get('memory_limit');
            $value = '256M';
            if ((int)$value >= self::MIN_MEMORY) {
                $code = self::OK;
            } else {
                $code = self::ERROR;
                $value = self::MEMORY_TO_LOW . $value;
                $hint = 'https://help.cobby.io';
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $hint = 'https://help.cobby.io';
        }

        $fieldValue = $this->htmlBuilder($value, $code, $hint);

        return $this->getFieldHtml($fieldset, 'memory', $label, $fieldValue);
    }

    private function checkCredentials($fieldset)
    {
        $label = __('Credentials');
        $hint = '';

        $url = $this->getApiUrl();
        $data = $this->_getLoginData();
        //$data = false;

        if ($data) {
            //$login = $this->_login($url, $data);
            $login = false;
            if ($login) {
                $code = self::OK;
                $value = self::LOGIN_SUCCEED;
            } else {
                $code = self::ERROR;
                $value = self::LOGIN_FAILED;
                $hint = 'https://help.cobby.io';
            }
        } else {
            $code = self::EXCEPTION;
            $value = self::NO_DATA;
            $hint = 'https://help.cobby.io';
        }

        $fieldValue = $this->htmlBuilder($value, $code, $hint);

        return $this->getFieldHtml($fieldset, 'credits', $label, $fieldValue);
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

        if ($apiUserName && $apiKey) {
            $data = array(
                "username" => $apiUserName,
                "password" => $apiKey
            );

            return json_encode($data);
        }

        return false;

    }

    protected function _login($url, $data)
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
                //$header = substr($response, 0, $header_size);
                $token = json_decode(substr($response, $header_size));

                curl_close($ch);

                if ($http_code !== 200) {
                    Mage::throwException("Http code: " .$http_code);
                }

                if ($token) {
                    return true;
                }

                return false;

                $errorMsg = $body->error->message;
                $error = array('401' => $errorMsg);
                return $error;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $error = array('400' => $msg);

                return false;
            }
        }

        return false;

        return 'Not a valid url';
    }

    private function htmlBuilder($value, $code, $hint)
    {
        $result = '';
        $link = "<a target='_blank'
                  href=$hint>" .
            __("Get help") .
            "</a>" . "</div>";

        switch ($code) {
            case self::ERROR:
                $result = '<div class="error">';
                $result .= $value."</div>";
                $result .= $link;
                break;
            case self::EXCEPTION:
                $result = '<div class="exception">';
                $result .= $value. "</div>";
                $result .= $link;
                break;
            case self::OK:
                $result = '<div>';
                $result .= $value;
                $result .= '<div class="ok">' . __(' OK') . "</div></div>";
                break;
        }

        return $result;
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