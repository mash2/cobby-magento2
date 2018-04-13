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

    /**
     * @var \Magento\Framework\Session\SessionManager
     */
    private $sessionManager;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @var \Magento\Authorization\Model\CompositeUserContext
     */
    private $compositeUserContext;

    /**
     * @var \Magento\User\Model\User
     */
    private $user;

    /**
     * Queue constructor.
     * @param \Magento\Framework\Session\SessionManager $sessionManager
     * @param UserContextInterface $userContext
     * @param \Magento\Authorization\Model\CompositeUserContext $compositeUserContext
     * @param \Magento\User\Model\User $user
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Authorization\Model\UserContextInterface $userContext,
        \Magento\Authorization\Model\CompositeUserContext $compositeUserContext,
        \Magento\User\Model\User $user,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->sessionManager = $sessionManager;
        $this->userContext = $userContext;
        $this->compositeUserContext = $compositeUserContext;
        $this->user = $user;
    }

    protected function _construct(
    )
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
        $session = $this->sessionManager;
        $user = $this->userContext;

        $userType = '';
        $sessionName = '';

        if ($user){
            $userType = $user ->getUserType();
            if ($userType == UserContextInterface::USER_TYPE_ADMIN){
                $userId = $this->compositeUserContext->getUserId();
                $userName = $this->user->load($userId)->getUserName();
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