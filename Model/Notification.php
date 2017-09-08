<?php
namespace Mash2\Cobby\Model;

class Notification implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $settings;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @param \Mash2\Cobby\Helper\Settings $settings
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Framework\UrlInterface $urlBuilder
    )
    {
        $this->settings = $settings;
        $this->urlBuilder = $urlBuilder;
    }

    public function getIdentity()
    {
        return md5('NO_COBBY_LICENSE');
    }

    public function isDisplayed()
    {
        $apiUser = $this->settings->getApiUser();
        return empty($apiUser);
    }

    public function getText()
    {
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/cobby');
        return __('Cobby has been installed. Click <a href="%1"> here </a> to set up your account.', $url);
    }

    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}