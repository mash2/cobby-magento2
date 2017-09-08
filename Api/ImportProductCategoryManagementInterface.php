<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductCategoryManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}
