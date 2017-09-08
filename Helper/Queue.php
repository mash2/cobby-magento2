<?php

namespace Mash2\Cobby\Helper;

/**
 * Class Queue
 * @package Mash2\Cobby\Helper
 */
class Queue extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Mysql TEXT Column is (64 Kilobytes) 65535 chars
     * UTF-8 space consumption is between 1 to 4 bytes per char
     * to be safe and have a reasonable performance we just use 10000
     */
    const MAX_MYSQL_TEXT_SIZE                   = 10000;

    /**
     * @var \Mash2\Cobby\Model\QueueFactory
     */
    private $queueFactory;

    /**
     * @var \Mash2\Cobby\Helper\CobbyApi
     */
    private $cobbyApi;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Mash2\Cobby\Helper\Settings
     */
    private $cobbySettings;

    /**
     * constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param CobbyApi $cobbyApi
     * @param \Mash2\Cobby\Model\QueueFactory $queueFactory
     * @param \Magento\Framework\Registry $registry
     * @param Settings $cobbySettings
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Mash2\Cobby\Helper\CobbyApi $cobbyApi,
        \Mash2\Cobby\Model\QueueFactory $queueFactory,
        \Magento\Framework\Registry $registry,
        \Mash2\Cobby\Helper\Settings $cobbySettings
    ) {
        parent::__construct($context);
        $this->queueFactory = $queueFactory;
        $this->cobbyApi = $cobbyApi;
        $this->registry = $registry;
        $this->cobbySettings = $cobbySettings;
    }

    /**
     * save changes to queue in batches
     *
     * @param $entity
     * @param $action
     * @param $ids
     * @return array
     */
    private function enqueue($entity, $action, $ids)
    {
        $result = array();
        $batches = $this->splitObjectIds($ids);
        foreach ($batches as $batch) {
            $queue = $this->queueFactory->create();
            $queue->setObjectIds($batch);
            $queue->setObjectEntity($entity);
            $queue->setObjectAction($action);
            $queue->save();
            $result[] = $queue->getId();
        }

        return $result;
    }

    /**
     * save changes to queue and notify cobby service
     *
     * @param $entity
     * @param $ids
     * @param $action
     */
    public function enqueueAndNotify($entity, $action, $ids)
    {
        if ($this->registry->registry('is_cobby_import') == 1) { //do nothing if is cobby import
            return;
        }

        $manageStock = $this->cobbySettings->getManageStock();

        if ($manageStock == \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_ENABLED ||
            $manageStock == \Mash2\Cobby\Helper\Settings::MANAGE_STOCK_READONLY ||
            $entity != 'stock'){
            try {
                $queueIds = $this->enqueue($entity, $action, $ids);
                //notify only with with the id from the first batch
                $this->cobbyApi->notifyCobbyService($entity, $action, $queueIds[0]);

            } catch (\Exception $e) {

            }
        }

    }

    /**
     * split string by MAX_MYSQL_TEXT column
     *
     * @param $ids
     * @return array
     */
    private function splitObjectIds($ids)
    {
        $objectIdsAsString = $ids;
        if (is_array($ids)) {
            $objectIdsAsString = implode('|', $ids);
        }

        $result = array();

        if (strlen($objectIdsAsString) < self::MAX_MYSQL_TEXT_SIZE) {
            $result[] = $objectIdsAsString;
        } else {
            while (true) {
                $objectIdsPart = substr($objectIdsAsString, 0, self::MAX_MYSQL_TEXT_SIZE);
                $lastPos = strrpos($objectIdsPart, "|");

                if ($lastPos > 0) {
                    $result[] = ltrim(substr($objectIdsPart, 0, $lastPos), "|");
                    $objectIdsAsString = substr($objectIdsAsString, $lastPos + 1);
                } else {
                    $result[] = $objectIdsPart;
                }

                if ($lastPos === false) {
                    break;
                }
            }
        }
        return $result;
    }
}