<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductUrlManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}
