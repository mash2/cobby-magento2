<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductLinkManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}
