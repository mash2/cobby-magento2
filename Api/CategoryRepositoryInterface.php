<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface CategoryRepositoryInterface
{
    /**
     *
     * @api
     * @param integer $storeId
     * @return mixed
     */
    public function getList($storeId);
}
