<?php

namespace Mash2\Cobby\Block\System\Config;

use Magento\Backend\Block\Context;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\LayoutFactory;
use Mash2\Cobby\Helper\SystemCheckHelper;

/**
 * Class Troubleshooter
 * @package Mash2\Cobby\Block\System\Config
 */
class SystemCheck extends Fieldset
{
    const MEMORY = 'memory';
    const PHP_VERSION = 'phpVersion';
    const CREDENTIALS = 'credentials';

    /**
     * @var \Magento\Framework\View\LayoutFactory
     */
    private $_layoutFactory;

    private $systemCheckHelper;

    /**
     * @param Context $context
     * @param Js $jsHelper
     * @param Session $authSession
     * @param LayoutFactory $layoutFactory
     * @param SystemCheckHelper $systemCheckHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Js $jsHelper,
        Session $authSession,
        LayoutFactory $layoutFactory,
        SystemCheckHelper $systemCheckHelper,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);

        $this->_layoutFactory = $layoutFactory;
        $this->systemCheckHelper = $systemCheckHelper;
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

        $sectionValue = $this->systemCheckHelper->getElement(self::PHP_VERSION);

        $fieldValue = $this->htmlBuilder($sectionValue);

        return $this->getFieldHtml($fieldset, 'phpversion', $label, $fieldValue);
    }

    private function getMemory($fieldset)
    {
        $label = __("Memory");

        $sectionValue = $this->systemCheckHelper->getElement(self::MEMORY);

        $fieldValue = $this->htmlBuilder($sectionValue);

        return $this->getFieldHtml($fieldset, 'memory', $label, $fieldValue);
    }

    private function checkCredentials($fieldset)
    {
        $label = __('Credentials');

        $sectionValue = $this->systemCheckHelper->getElement(self::CREDENTIALS);

        $fieldValue = $this->htmlBuilder($sectionValue);

        return $this->getFieldHtml($fieldset, 'credits', $label, $fieldValue);
    }

    private function htmlBuilder($sectionValue)
    {
        $result = '';
        $value = $sectionValue[SystemCheckHelper::VALUE];
        $code = $sectionValue[SystemCheckHelper::CODE];
        $url = $sectionValue[SystemCheckHelper::LINK];
        $link = "<a target='_blank'href=$url>" . __("Get help") . "</a>" . "</div>";

        switch ($code) {
            case SystemCheckHelper::ERROR:
                $result = '<div class="error">';
                $result .= $value."</div>";
                $result .= $link;
                break;
            case SystemCheckHelper::EXCEPTION:
                $result = '<div class="exception">';
                $result .= $value. "</div>";
                $result .= $link;
                break;
            case SystemCheckHelper::OK:
                $result = '<div class="ok">' . $value . "</div></div>";
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