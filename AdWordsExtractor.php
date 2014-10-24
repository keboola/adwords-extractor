<?php

namespace Keboola\AdWordsExtractorBundle;

use Keboola\AdWordsExtractorBundle\AdWords\AppConfiguration;
use Keboola\Csv\CsvFile;
use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;
use Syrup\ComponentBundle\Exception\UserException;

class AdWordsExtractor extends Extractor
{
	/**
	 * @var Client
	 */
	protected $storageApi;
	protected $name = "adwords";
	protected $files;

	protected $appName;
	protected $clientId;
	protected $clientSecret;

	protected $tables = array(
		'customers' => array(
			'columns' => array('name', 'companyName', 'customerId', 'canManageClients', 'currencyCode', 'dateTimeZone')
		),
		'campaigns' => array(
			'columns' => array('customerId', 'id', 'name', 'status', 'servingStatus', 'startDate', 'endDate',
				'adServingOptimizationStatus', 'advertisingChannelType', 'displaySelect')
		)
	);

	public function __construct(AppConfiguration $appConfiguration)
	{
		$this->appName = $appConfiguration->app_name;
		$this->clientId = $appConfiguration->client_id;
		$this->clientSecret = $appConfiguration->client_secret;
	}

	protected function prepareFiles()
	{
		$bucketId = sprintf("in.c-%s-%s", $this->getFullName(), $this->configName);
		if ($this->storageApi->bucketExists($bucketId)) foreach ($this->storageApi->listTables($bucketId) as $t) {
			$this->storageApi->dropTable($t['id']);
		}

		$this->incrementalUpload = true;
		foreach ($this->tables as $k => $v) {
			$f = new CsvFile($this->temp->createTmpFile());
			$f->writeRow($v['columns']);
			$this->files[$k] = $f;
		}
	}

	protected function saveToFile($table, $data)
	{
		if (!isset($this->tables[$table])) {
			throw new \Exception('Table ' . $table . ' not configured for the Extractor');
		}
		/** @var CsvFile $f */
		$f = $this->files[$table];

		$dataToSave = array();
		foreach ($this->tables[$table]['columns'] as $c) {
			$dataToSave[$c] = isset($data->$c)? $data->$c : null;
		}

		$f->writeRow($dataToSave);
	}

	protected function uploadFiles()
	{
		$this->sapiUpload($this->files);
	}

	public function run($config)
	{
		$timerAll = time();
		$params = $this->getSyrupJob()->getParams();
		$since = date('Ymd', strtotime(isset($params['since'])? $params['since'] : '-1 day'));
		$until = date('Ymd', strtotime(isset($params['until'])? $params['until'] : '-1 day'));

		$jobId = $params['config'] . '|' . $this->getSyrupJob()->getId();
		if (!$this->storageApi->getRunId()) {
			$this->storageApi->setRunId($this->getSyrupJob()->getId());
		}

		if (!isset($config['attributes']['developerToken'])) {
			throw new UserException('AdWords developerToken is not configured in configuration table');
		}
		if (!isset($config['attributes']['refreshToken'])) {
			throw new UserException('AdWords refreshToken is not configured in configuration table');
		}
		if (!isset($config['attributes']['customerId'])) {
			throw new UserException('AdWords customerId is not configured in configuration table');
		}
		if (!isset($config['data']) || !count($config['data'])) {
			throw new UserException('Configuration table contains no rows');
		}
		if (!isset($config['data'][0]['table']) || !isset($config['data'][0]['query'])) {
			throw new UserException('Configuration table does not contain required columns table and query');
		}


		$this->prepareFiles();

		$api = new AdWords\Api(
			$this->clientId,
			$this->clientSecret,
			$config['attributes']['developerToken'],
			$config['attributes']['refreshToken'],
			$this->appName,
			$this->temp
		);

		$api->setCustomerId($config['attributes']['customerId']);
		$customers = $api->getCustomers();
		$counter = 1;
		$counterTotal = count($customers);
		foreach ($customers as $customer) if (in_array($customer->customerId, array(6874151532, 8971537363, 3272849305, 1924422140))) {
			$timer = time();
			$this->saveToFile('customers', $customer);

			$api->setCustomerId($customer->customerId);
			foreach ($api->getCampaigns($since, $until) as $campaign) {
				$campaign->customerId = $customer->customerId;
				$this->saveToFile('campaigns', $campaign);
			}

			foreach ($config['data'] as $configReport) {
				try {
					$api->getReport($configReport['query'], $since, $until, $configReport['table']);
				} catch (UserException $e) {
					$this->logEvent('Getting report for client ' . $customer->name . ' failed. ' . $e->getMessage(), $jobId, time() - $timer, Event::TYPE_WARN);
				}
			}

			$this->logEvent(sprintf('Data for client %s downloaded (%d of %d)', $customer->name, $counter, $counterTotal), $jobId, time() - $timer);
			$counter++;
		}

		$reportFiles = array();
		foreach ($api->getReportFiles() as $table => $file) {
			$reportFiles[$table] = new CsvFile($file);
		}
		$this->sapiUpload($reportFiles);
		$this->uploadFiles();
		$this->logEvent('Extraction complete', $jobId, time() - $timerAll, Event::TYPE_SUCCESS);
	}


	private function logEvent($message, $config, $duration=null, $type=Event::TYPE_INFO)
	{
		$event = new Event();
		$event
			->setType($type)
			->setMessage($message)
			->setComponent('ex-adwords')
			->setConfigurationId($config)
			->setRunId($this->storageApi->getRunId());
		if ($duration) {
			$event->setDuration($duration);
		}
		$this->storageApi->createEvent($event);
	}
}
