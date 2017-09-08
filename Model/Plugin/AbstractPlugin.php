<?php

namespace Mash2\Cobby\Model\Plugin;

/**
 * Class AbstractPlugin
 * @package Mash2\Cobby\Model\Plugin
 */
abstract class AbstractPlugin
{
    /**
     * @var \Mash2\Cobby\Helper\Queue
     */
    private $queueHelper;

    /**
     * @var \Mash2\Cobby\Model\Product
     */
    private $productModel;

    /**
     * constructor.
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

    /**
     * save changes to queue and notifiy cobby service
     *
     * @param $entity
     * @param $ids
     * @param $action
     */
    public function enqueueAndNotify($entity, $action, $ids)
    {
        $this->queueHelper->enqueueAndNotify($entity, $action, $ids);
    }

    /**
     * @param $prefix
     */
    public function resetHash($prefix){
        $this->productModel->resetHash($prefix);
    }

    /**
     * @param $ids
     */
    public function updateHash($ids){
        $this->productModel->updateHash($ids);
    }
}
