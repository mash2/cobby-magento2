<?php
namespace Mash2\Cobby\Model;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerGroupManagement implements \Mash2\Cobby\Api\CustomerGroupManagementInterface
{
    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->groupRepository = $groupRepository;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function getList()
    {
        $result = array();

        $searchCriteria = $this->searchCriteriaBuilder
            ->create();
        $customerGroups = $this->groupRepository->getList($searchCriteria)->getItems();

        foreach($customerGroups as $customerGroup){
            $result[] = array(
                'group_id'  => $customerGroup->getId(),
                'name'      => $customerGroup->getCode()
            );
        }

        return $result;
    }
}
