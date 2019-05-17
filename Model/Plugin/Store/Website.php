<?php
namespace Mash2\Cobby\Model\Plugin\Store;

class Website extends \Mash2\Cobby\Model\Plugin\AbstractPlugin
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
        \Magento\Store\Model\ResourceModel\Website $websiteResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $website
    ) {
        $websiteResource->addCommitCallback(function () use ($website) {
           $this->resetHash('website_changed');
        });

        $this->state->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID);

        return $proceed($website);
    }

    public function aroundDelete(
        \Magento\Store\Model\ResourceModel\Website $websiteResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $website
    ){
        $this->resetHash('website_changed');

        $this->state->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID);

        return $proceed($website);
    }

}