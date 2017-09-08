<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductGroupedManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}
