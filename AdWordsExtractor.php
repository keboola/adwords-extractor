<?php

namespace Keboola\AdWordsExtractorBundle;

use Keboola\AdWordsExtractorBundle\Extractor\AdWords;
use Keboola\AdWordsExtractorBundle\Extractor\AppConfiguration;
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
			'columns' => array('name', 'login', 'companyName', 'customerId', 'canManageClients', 'currencyCode',
				'dateTimeZone')
		),
		'campaigns' => array(
			'columns' => array('customerId', 'id', 'name', 'campaignStatus', 'servingStatus', 'startDate', 'endDate',
				'adServingOptimizationStatus', 'advertisingChannelType', 'displaySelect', 'trackingUrlTemplate')
		),
		'stats' => array(
			'columns' => array('avgCPC', 'avgCPM', 'avgPosition', 'campaignID', 'campaign', 'clicks', 'cost', 'day',
				'impressions', 'network')
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

		$aw = new AdWords(
			$this->clientId,
			$this->clientSecret,
			$config['attributes']['developerToken'],
			$config['attributes']['refreshToken'],
			$this->appName,
			$this->temp
		);

		$aw->setCustomerId($config['attributes']['customerId']);
		foreach ($aw->getCustomers() as $customer) {
			$timer = time();
			$this->saveToFile('customers', $customer);

			$aw->setCustomerId($customer->customerId);
			foreach ($aw->getCampaigns($since, $until) as $campaign) {
				$campaign->customerId = $customer->customerId;
				$this->saveToFile('campaigns', $campaign);
			}

			foreach ($config['data'] as $configReport) {
				$report = $aw->getReport($configReport['query'], $since, $until);
				if ($report) {
					$this->sapiUpload(array($configReport['table'] => $report));
				}
			}

			$this->logEvent('Data for client ' . $customer->name . ' downloaded', time() - $timer);
		}

		$this->uploadFiles();
		$this->logEvent('Extraction complete', time() - $timerAll);
	}


	private function logEvent($message, $duration=null)
	{
		$event = new Event();
		$event
			->setType(Event::TYPE_INFO)
			->setMessage($message)
			->setComponent('ex-adwords')
			//->setConfigurationId()
			->setRunId($this->storageApi->getRunId());
		if ($duration) {
			$event->setDuration($duration);
		}
		$this->storageApi->createEvent($event);
	}
}
