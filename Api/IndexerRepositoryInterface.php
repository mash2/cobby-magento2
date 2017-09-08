<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface IndexerRepositoryInterface
{
    /**
     *
     * @api
     * @return mixed
     */
    public function export();

    /**
     * @api
     * @param string $jsonData
     * @return bool
     */
    public function reindex($jsonData);
}
