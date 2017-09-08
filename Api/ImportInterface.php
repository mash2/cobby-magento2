<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface ImportInterface
{
    /**
     * @api
     * @param string $jsonData
     * @return mixed
     */
    public function importProducts($jsonData);

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
     * @return array
     */
    public function importProductBundle($jsonData);

    /**
     * @return bool
     */
    public function importProductsStart();

    /**
     * @param \Mash2\Cobby\Api\Data\ImportProductsFinishInterface $data
     * @return bool
     */
    public function importProductsFinish(\Mash2\Cobby\Api\Data\ImportProductsFinishInterface $data);

}
