<?php
namespace Mash2\Cobby\Model\Plugin\Store;


class Store extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
{
    private $state;
    private $messageManager;

    public function __construct(
        \Mash2\Cobby\Helper\Queue $queueHelper,
        \Mash2\Cobby\Model\ProductFactory $productFactory,
        \Magento\Indexer\Model\Indexer\State $state,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ){
        $this->state = $state;
        $this->messageManager = $messageManager;

        parent::__construct($queueHelper,$productFactory);
    }

    public function aroundSave(
        \Magento\Store\Model\ResourceModel\Store $storeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $store
    ) {
        $storeResource->addCommitCallback(function () use ($store) {
            $this->resetHash('store_changed');
        });

        $this->state->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID);

        return $proceed($store);
    }

    public function aroundDelete(
        \Magento\Store\Model\ResourceModel\Store $storeResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $store
    ){
        $this->resetHash('store_changed');

        $this->state->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID);

        return $proceed($store);
    }
}