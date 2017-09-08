<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface AttributeManagementInterface
{
    /**
     *
     * Retrieve related attributes based on given attribute set ID
     *
     * @api
     * @param integer $attributeSetId
     * @return array
     */
    public function getList($attributeSetId);

    /**
     *
     * Retrieve attribute options
     *
     * @api
     * @param integer $attributeId
     * @return array
     */
    public function getOptions($attributeId);
}
