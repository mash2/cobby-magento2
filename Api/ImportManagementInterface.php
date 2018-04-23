<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportManagementInterface
{
    /**
     *
     * @api
     * @param string $jsonData
     * @param string $transactionId
     * @return mixed
     */
    public function importProducts($jsonData, $transactionId);

    /**
     * @api
     * @param string $jsonData
     * @return bool
     */
    public function importProductLinks($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return bool
     */
    public function importProductCategories($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductTierPrices($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductStocks($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductImages($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductGrouped($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductConfigurable($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductUrls($jsonData);

    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProductBundle($jsonData);
}
