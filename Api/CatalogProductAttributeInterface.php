<?php
namespace Mash2\Cobby\Api;

/**
 * Interface CatalogProductAttributeInterface
 * @api
 */
interface CatalogProductAttributeInterface
{
    /**
     *
     * Retrieve related attributes based on given attribute set ID
     *
     * @api
     * @param int $attributeSetId
     * @param int $attributeId
     * @return mixed
     */
    public function export($attributeSetId = null, $attributeId = null);

//    /**
//     * Retrieve related attribute based on given attributeId
//     *
//     * @api
//     * @param integer $attributeId
//     * @return object
//     */
//    public function info($attributeId);
}