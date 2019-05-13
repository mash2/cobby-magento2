<?php


namespace Mash2\Cobby\Observer;

use Magento\Framework\Event\ObserverInterface;

class StoresChanged implements ObserverInterface
{
    private $state;

    public function __construct(
        \Magento\Indexer\Model\Indexer\State $state,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->state = $state;
        //$this->messageManager = $messageManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {


        $this->state->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID);


        //$this->messageManager->addSuccess(self::SUCCESS_MESSAGE);


        return $this;
    }
}