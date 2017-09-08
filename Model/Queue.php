<?php
namespace Mash2\Cobby\Model;

use \Magento\Authorization\Model\UserContextInterface;

/**
 * Class Queue
 * @package Mash2\Cobby\Model
 */
class Queue extends \Magento\Framework\Model\AbstractModel
{
    const CONTEXT_NONE          = 'none';
    const CONTEXT_BACKEND       = 'backend';
    const CONTEXT_FRONTEND      = 'frontend';
    const CONTEXT_API           = 'api';
    const ADMIN_SESSION         = 'admin';
    const WEBAPI_REST_SESSION   = 'PHPSESSID';

    protected function _construct()
    {
        $this->_init('Mash2\Cobby\Model\ResourceModel\Queue');
    }

    public function beforeSave()
    {
        $result = $this->getCurrentContext();
        $this->addData($result);

        parent::beforeSave();
    }

    private function getCurrentContext()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $session = $objectManager->get('\Magento\Framework\Session\SessionManager');
        $user = $objectManager->get('Magento\Authorization\Model\UserContextInterface');

        $userType = '';
        $sessionName = '';

        if ($user){
            $userType = $user ->getUserType();
            if ($userType == UserContextInterface::USER_TYPE_ADMIN){
                $userId = $objectManager->get('\Magento\Authorization\Model\CompositeUserContext')->getUserId();
                $userName = $objectManager->get('Magento\User\Model\User')->load($userId)->getUserName();
            }
        }

        if ($session){
            $sessionName = $session->getName();
        }

        if ($userType == UserContextInterface::USER_TYPE_ADMIN && $sessionName == self::ADMIN_SESSION){

            return array('user_name' => $userName, 'context' => self::CONTEXT_BACKEND);

        }elseif ($userType == UserContextInterface::USER_TYPE_ADMIN && $sessionName == self::WEBAPI_REST_SESSION){

            return array('user_name' => $userName, 'context' => self::CONTEXT_API);

        }elseif ($userType == UserContextInterface::USER_TYPE_CUSTOMER){

            return array('user_name' => 'customer', 'context' => self::CONTEXT_FRONTEND);
        }elseif ($userType == UserContextInterface::USER_TYPE_GUEST) {

            return array('user_name' => 'guest', 'context' => self::CONTEXT_FRONTEND);
        }

        return array('user_name' => ' ', 'context' => self::CONTEXT_NONE);
    }
}