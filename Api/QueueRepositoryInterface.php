<?php
namespace Mash2\Cobby\Api;

/**
 * @api
 */
interface QueueRepositoryInterface
{
    /**
     *
     * @api
     * @return integer
     */
    public function getMax();

    /**
     *
     * @api
     * @param integer $minQueueId
     * @param integer $pageSize
     * @return \Mash2\Cobby\Api\Data\QueueInterface[]
     */
    public function getList($minQueueId, $pageSize);

    /**
     *
     * @api
     * @return int The number of affected rows.
     */
    public function delete();
}
