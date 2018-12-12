<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ConfigManagementInterface
{
    /**
     *
     * @api
     * @return mixed
     */
    public function getList();

    /**
     * @api
     * @param string $jsonData
     * @return bool
     */
    public function active($jsonData);
}
