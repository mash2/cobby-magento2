<?php
/**
 * Created by PhpStorm.
 * User: mash2
 * Date: 20.08.18
 * Time: 10:28
 */

namespace Mash2\Cobby\Api;

/**
 * Interface ImportProductCustomOptionManagementInterface
 * @api
 * @package Mash2\Cobby\Api
 */
interface ImportProductCustomOptionManagementInterface
{
    /**
     * @api
     * @param array $rows
     * @return mixed
     */
    public function import($rows);
}