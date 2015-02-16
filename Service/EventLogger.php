<?php
/**
 * @package adwords-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Service;

use Keboola\StorageApi\Client as StorageApiClient,
    Keboola\StorageApi\Event as StorageApiEvent;


class EventLogger
{
    private $appName;
    private $storageApiClient;
    private $jobId;

    const TYPE_INFO = StorageApiEvent::TYPE_INFO;
    const TYPE_ERROR = StorageApiEvent::TYPE_ERROR;
    const TYPE_SUCCESS = StorageApiEvent::TYPE_SUCCESS;
    const TYPE_WARN = StorageApiEvent::TYPE_WARN;

    public function __construct($appName, StorageApiClient $storageApiClient, $jobId)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApiClient;
        $this->jobId = $jobId;
    }

    public function log($message, $params=array(), $duration=null, $type=self::TYPE_INFO)
    {
        $event = new StorageApiEvent();
        $event
            ->setType($type)
            ->setMessage($message)
            ->setComponent($this->appName)
            ->setConfigurationId($this->jobId)
            ->setRunId($this->storageApiClient->getRunId());
        if (count($params)) {
            $event->setParams($params);
        }
        if ($duration)
            $event->setDuration($duration);
        $this->storageApiClient->createEvent($event);
    }

}