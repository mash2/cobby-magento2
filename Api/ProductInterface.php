<?php
namespace Mash2\Cobby\Api;

/**
 * Interface ProductInterface
 *
 * @api
 * @package Mash2\Cobby\Api
 */
interface ProductInterface
{
    /**
     * @api
     * @param string $hash
     * @return mixed
     */
    public function resetHash($hash);

    /**
     *
     * @api
     * @param integer $ids
     * @return mixed
     */
    public function updateHash($ids);
}