<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ProductManagementInterface
{
    /**
     *
     * @api
     * @param integer $pageNum
     * @param integer $pageSize
     * @return mixed
     */
    public function getList($pageNum, $pageSize);

    /**
     *
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function updateSkus($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function updateWebsites($jsonData);
}
