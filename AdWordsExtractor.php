<?php

namespace Keboola\AdWordsExtractorBundle;

use Keboola\AdWordsExtractorBundle\Extractor\AdWords;
use Keboola\AdWordsExtractorBundle\Extractor\AppConfiguration;
use Keboola\Csv\CsvFile;
use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor;
use Keboola\StorageApi\Event;

class AdWordsExtractor extends Extractor
{
	protected $name = "adwords";
	protected $appName;
	protected $clientId;
	protected $clientSecret;

	protected $tables = array(
		'campaigns' => array(
			'columns' => array('customerId', 'id', 'name', 'status', 'servingStatus', 'startDate', 'endDate', 'budget',
				'conversionOptimizerEligibility', 'adServingOptimizationStatus', 'frequencyCap', 'settings',
				'advertisingChannelType', 'networkSetting', 'labels', 'biddingStrategyConfiguration',
				'forwardCompatibilityMap', 'displaySelect', 'trackingUrlTemplate', 'urlCustomParameters')
		),
		'clients' => array(
			'columns' => array('name', 'login', 'companyName', 'customerId', 'canManageClients', 'currencyCode',
				'dateTimeZone')
		)
	);

	public function __construct(AppConfiguration $appConfiguration)
	{
		$this->appName = $appConfiguration->app_name;
		$this->clientId = $appConfiguration->client_id;
		$this->clientSecret = $appConfiguration->client_secret;
	}

	public function run($config)
	{
		$aw = new AdWords(
			$this->clientId,
			$this->clientSecret,
			$config['attributes']['developerToken'],
			$config['attributes']['refreshToken'],
			$this->appName,
			$this->temp
		);

		$files = array();

		$aw->setCustomerId($config['attributes']['customerId']);
		$clients = $aw->getClients();
		if (count($clients)) {
			$files['clients'] = new CsvFile($this->temp->createTmpFile());
			$files['clients']->writeRow($this->tables['clients']['columns']);

			$files['campaigns']= new CsvFile($this->temp->createTmpFile());
			$files['campaigns']->writeRow($this->tables['campaigns']['columns']);

			foreach ($clients as $client) {

				$files['clients']->writeRow(self::getCsvRow($client, $this->tables['clients']['columns']));

				$aw->setCustomerId($client->customerId);
				foreach ($aw->getCampaigns() as $campaign) {
					$files['campaigns']->writeRow(self::getCsvRow($campaign, $this->tables['campaigns']['columns']));
				}

				$this->logEvent('Data for client ' . $client->name . ' downloaded');
			}
		}

		$this->sapiUpload($files, 'in.c-ex-adwords');
	}

	private static function getCsvRow($object, $properties)
	{
		$row = array();
		foreach ($properties as $p) {
			$row[$p] = isset($object->$p)? $object->$p : null;
		}
		return $row;
	}

	private function logEvent($message)
	{
		$event = new Event();
		$event
			->setType(Event::TYPE_INFO)
			->setMessage($message)
			->setComponent($this->appName)
			//->setConfigurationId()
			->setRunId($this->storageApi->getRunId());
		$this->storageApi->createEvent($event);
	}
}
