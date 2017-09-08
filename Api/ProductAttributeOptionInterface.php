<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ProductAttributeOptionInterface
{
    /**
     *
     * Retrieve attribute options
     *
     * @api
     * @param integer $attributeId
     * @return mixed
     */
    public function export($attributeId);

    /**
     * Imports options from cobby
     *
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function import($jsonData);
}