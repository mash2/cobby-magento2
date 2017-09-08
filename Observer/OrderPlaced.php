<?php
/**
 * Created by PhpStorm.
 * User: Slavko
 * Date: 14.03.2017
 * Time: 10:05
 */

namespace Mash2\Cobby\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderPlaced
 * @package Mash2\Cobby\Observer
 */
class OrderPlaced implements ObserverInterface
{
    /**
     * @var \Mash2\Cobby\Helper\Queue
     */
    private $queueHelper;

    /**
     * @var \Mash2\Cobby\Model\ProductFactory
     */
    private $productModel;

    /**
     * OrderPlaced constructor.
     * @param \Mash2\Cobby\Helper\Queue $queueHelper
     * @param \Mash2\Cobby\Model\ProductFactory $productFactory
     */
    public function __construct(
        \Mash2\Cobby\Helper\Queue $queueHelper,
        \Mash2\Cobby\Model\ProductFactory $productFactory
    ){
        $this->queueHelper = $queueHelper;
        $this->productModel = $productFactory->create();
    }

    public function execute(\Magento\Framework\Event\Observer $observer){
        $data = $observer->getEvent()->getOrder()->getData();

        $ids = array();
        foreach ($data['items'] as $item){
            $ids[] = $item->getData('product_id');
        }

        $this->queueHelper->enqueueAndNotify('product', 'save', $ids);
        $this->productModel->updateHash($ids);

        return;
    }
}