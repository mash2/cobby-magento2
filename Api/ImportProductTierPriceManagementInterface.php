<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportProductTierPriceManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}
