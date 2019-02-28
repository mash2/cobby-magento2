<?php
/**
 * Created by PhpStorm.
 * User: slavko
 * Date: 28.02.19
 * Time: 12:52
 */

namespace Mash2\Cobby\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Troubleshooter extends Action
{
    protected $resultJsonFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory
        //Data $helper
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        //$this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            //$this->_getSyncSingleton()->collectRelations();
        } catch (\Exception $e) {
            $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
        }

        //$lastCollectTime = $this->helper->getLastCollectTime();
        $lastCollectTime = '12:30';
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData(['success' => true, 'time' => $lastCollectTime]);
    }

    /**
     * Return product relation singleton
     *
     * @return \MageWorx\AlsoBought\Model\Relation
     */
    protected function _getSyncSingleton()
    {
        return $this->_objectManager->get('MageWorx\AlsoBought\Model\Relation');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MageWorx_AlsoBought::config');
    }
}