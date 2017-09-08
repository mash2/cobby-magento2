<?php
namespace Mash2\Cobby\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class CoreConfigDataSave
 * @package Mash2\Cobby\Observer
 */
class CoreConfigDataSave implements ObserverInterface
{
    /**
     * @var \Mash2\Cobby\Helper\Queue
     */
    private $queue;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * @var \Mash2\Cobby\Model\Product
     */
    private $productFactory;

    /**
     * CoreConfigDataSave constructor.
     * @param \Mash2\Cobby\Model\ProductFactory $productFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    public function __construct(
        \Mash2\Cobby\Model\ProductFactory $productFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $config

    ){
        $this->productFactory = $productFactory->create();
        $this->config = $config;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
//        $data = $observer->getEvent();
//        //$data = $data->getDataByPath();
//
//        $newScope = $data->getPriceScope();
//
//        $currentScope = $this->config->getValue(\Magento\Store\Model\Store::XML_PATH_PRICE_SCOPE);
//
//        if($newScope != $currentScope){
//
//            $this->productFactory->resetHash('config_price_changed');
//            return;
//        }
        return;
    }
}