<?php
namespace Mash2\Cobby\Api\Data;

/**
 * @api
 */
interface QueueInterface
{
    const ID = 'queue_id';

    /**
     * Queue Id
     *
     * @return integer
     */
    public function getQueueId();


}
