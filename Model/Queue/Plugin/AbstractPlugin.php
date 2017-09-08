<?php

namespace Mash2\Cobby\Model\Queue\Plugin;

abstract class AbstractPlugin
{
    /**
     * @var \Mash2\Cobby\Helper\Queue
     */
    private $queueHelper;

    /**
     * constructor.
     * @param \Mash2\Cobby\Helper\Queue $queueHelper
     */
    public function __construct(\Mash2\Cobby\Helper\Queue $queueHelper)
    {
        $this->queueHelper = $queueHelper;
    }

    /**
     * save changes to queue and notifiy cobby service
     *
     * @param $entity
     * @param $ids
     * @param $action
     */
    public function enqueueAndNotify($entity, $action, $ids)
    {
        $this->queueHelper->enqueueAndNotify($entity, $action, $ids);
    }
}
