<?php

namespace Keboola\AdWordsExtractor;

use Keboola\AdWordsExtractor\Extractor\ConfigurationStorage;
use Keboola\AdWordsExtractor\Extractor\UserStorage;
use Keboola\AdWordsExtractor\Service\EventLogger;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\Syrup\Exception\UserException;
use Keboola\Syrup\Job\Metadata\Job;
use Keboola\Temp\Temp;
use Monolog\Logger;
use ReportDownloadException;

class JobExecutor extends \Keboola\Syrup\Job\Executor
{
	/**
	 * @var Client
	 */
	protected $storageApi;
    /**
     * @var UserStorage
     */
    protected $userStorage;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var EventLogger
     */
    protected $eventLogger;

	protected $appName;
	protected $clientId;
	protected $clientSecret;



    public function __construct($appName, $config, Temp $temp, Logger $logger)
	{
        if (!isset($config['client_id'])) {
            throw new \Exception('Parameter ex_adwords.client_id is missing');
        }
        if (!isset($config['client_secret'])) {
            throw new \Exception('Parameter ex_adwords.client_secret is missing');
        }
		$this->appName = $appName;
		$this->clientId = $config['client_id'];
		$this->clientSecret = $config['client_secret'];
        $this->temp = $temp;
        $this->logger = $logger;
	}

    public function execute(Job $job)
    {
        $configurationStorage = new ConfigurationStorage($this->appName, $this->storageApi);
        $this->eventLogger = new EventLogger($this->appName, $this->storageApi, $job->getId());
        $this->userStorage = new UserStorage($this->appName, $this->storageApi, $this->temp);

        $params = $job->getParams();
        $configIds = isset($params['config'])? array($params['config']) : $configurationStorage->getConfigurationsList();
        $since = date('Ymd', strtotime(isset($params['since'])? $params['since'] : '-1 day'));
        $until = date('Ymd', strtotime(isset($params['until'])? $params['until'] : '-1 day'));

        foreach ($configIds as $configId) {
            $configuration = $configurationStorage->getConfiguration($configId);
            $this->extract($configId, $configuration['attributes'], $configuration['data'], $since, $until);
        }
    }

    public function extract($configId, $attributes, $reports, $since, $until)
    {
        $timerAll = time();
        $api = new AdWords\Api(
            $this->clientId,
            $this->clientSecret,
            $attributes['developerToken'],
            $attributes['refreshToken'],
            $this->appName,
            $this->temp,
            $this->logger
        );
        $api->setCustomerId($attributes['customerId']);
        $customers = $api->getCustomers($since, $until);
        $counter = 1;
        $counterTotal = count($customers);
        foreach ($customers as $customer) {
            $timer = time();
            $this->userStorage->save('customers', $customer);

            // Get start time of earliest campaign and end time of latest campaign
            $clientStartTime = time();
            $clientEndTime = strtotime('2000-01-01');

            $api->setCustomerId($customer->customerId);
            foreach ($api->getCampaigns($since, $until) as $campaign) {
                $campaign->customerId = $customer->customerId;
                $this->userStorage->save('campaigns', $campaign);

                if (strtotime($campaign->startDate) < $clientStartTime) {
                    $clientStartTime = strtotime($campaign->startDate);
                }
                if (strtotime($campaign->endDate) > $clientEndTime) {
                    $clientEndTime = strtotime($campaign->endDate);
                }
            }

            // Download reports only if there is an active campaign
            if ($clientStartTime <= strtotime($until) && $clientEndTime >= strtotime($since)) {
                foreach ($reports as $configReport) {
                    try {
                        $api->getReport($configReport['query'], $since, $until, $configReport['table']);
                    } catch (ReportDownloadException $e) {
                        throw new UserException(sprintf('Getting report for client %s failed. %s', $customer->name, $e->getMessage()), $e);
                    }
                }
            }

            $this->eventLogger->log(
                sprintf('Data for client %s downloaded (%d of %d)', $customer->name, $counter, $counterTotal),
                [],
                time() - $timer,
                EventLogger::TYPE_SUCCESS
            );
            $counter++;
        }

        $this->userStorage->uploadData($configId);
        foreach ($api->getReportFiles() as $table => $file) {
            $this->userStorage->uploadTable($configId, $table, new CsvFile($file));
        }

        $this->eventLogger->log('Extraction complete', [], time() - $timerAll, EventLogger::TYPE_SUCCESS);
    }
}
