<?php
namespace Mash2\Cobby\Observer;

use Magento\Framework\Event\ObserverInterface;

class SaveConfig implements ObserverInterface
{
    const SUCCESS_MESSAGE = 'Registration was successful. Excel is now linked to your store. The service is now being set up for the first use. This process can take some time. Once done, you will receive an email with further information.';
    /**
     * @var \Mash2\Cobby\Helper\CobbyApi
     */
    private $cobbyApi;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $settings;

    /**
     * SaveConfig constructor.
     * @param \Mash2\Cobby\Helper\CobbyApi $cobbyApi
     */
    public function __construct(
        \Mash2\Cobby\Helper\CobbyApi $cobbyApi,
        \Mash2\Cobby\Helper\Settings $settings,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->cobbyApi = $cobbyApi;
        $this->settings = $settings;
        $this->messageManager = $messageManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $apiUser = $this->settings->getApiUser();
        $apiPassword = $this->settings->getApiPassword();

        $this->cobbyApi->registerShop($apiUser, $apiPassword);

        $this->messageManager->addSuccess(self::SUCCESS_MESSAGE);

        //TODO: INdex auf process setzen ?
//        Mage::getSingleton('index/indexer')
//            ->getProcessByCode('cobby_sync')
//            ->changeStatus(Mage_Index_Model_Process::STATUS_RUNNING);

        //TODO: Cache leeren?
        // clean cache
//        Mage::app()->getCacheInstance()->cleanType('config');

        return $this;
    }
}
