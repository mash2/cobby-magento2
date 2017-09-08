<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductBundleManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return array
     */
    public function import($rows);
}