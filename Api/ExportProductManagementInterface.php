<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ExportProductManagementInterface
{
    /**
     *
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function export($jsonData);
}
