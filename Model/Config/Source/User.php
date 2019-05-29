<?php
namespace Mash2\Cobby\Model\Config\Source;

use \Magento\User\Model\ResourceModel\User\Collection;
use \Magento\Framework\Authorization\PolicyInterface;

class User implements \Magento\Framework\Option\ArrayInterface
{
    private $policyInterface;
    private $userCollection;
    private $options;

    public function __construct(
        PolicyInterface $policyInterface,
        Collection $userCollection
    ){
        $this->policyInterface = $policyInterface;
        $this->userCollection = $userCollection;
    }

    public function toOptionArray()
    {
        if (!$this->options) {

            $users =  $this->userCollection->load();

            foreach($users as $user)
            {
                if(count($user->getRoles()) == 0)
                    continue;

                $isAllowed = $this->policyInterface->isAllowed($user->getAclRole(), null) ||
                    $this->policyInterface->isAllowed($user->getAclRole(), 'Mash2_Cobby::cobby');

                if($isAllowed) {
                    $this->options[] = array(
                        'label' => $user->getUsername(),
                        'value' => $user->getUserId()
                    );
                }
            }
        }

        return $this->options;
    }
}