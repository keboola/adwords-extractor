<?
namespace Keboola\AdWordsExtractorBundle\Extractor;

use AdWordsUser;
use DateRange;
use Predicate;
use ReportDefinition;
use ReportDownloadException;
use ReportUtils;
use Selector;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use Syrup\ComponentBundle\Filesystem\Temp;

class AdWordsException extends SyrupComponentException
{

}

class AdWords
{

	/**
	 * Number of retries for one API call
	 */
	const RETRIES_COUNT = 5;
	/**
	 * Back off time before retrying API call
	 */
	const BACKOFF_INTERVAL = 60;

	/**
	 * @var AdWordsUser
	 */
	private $user;

	private $temp;

	public function __construct($clientId, $clientSecret, $developerToken, $refreshToken, $userAgent, Temp $temp)
	{
		$this->temp = $temp;

		$this->user = new AdWordsUser();
		$this->user->SetDeveloperToken($developerToken);
		$this->user->SetOAuth2Info(array(
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'refresh_token' => $refreshToken
		));
		$this->user->SetUserAgent($userAgent);
		try {
			$handler = $this->user->GetOAuth2Handler();
			$credentials = $handler->RefreshAccessToken($this->user->GetOAuth2Info());
			$this->user->SetOAuth2Info($credentials);
		} catch (\Exception $e) {
			throw new AdWordsException($e->getMessage(), 500, $e);
		}
	}

	public function setCustomerId($customerId)
	{
		$this->user->SetClientCustomerId($customerId);
	}

	public function selectorRequest($service, $fields, $predicates=array(), $since=null, $until=null)
	{
		$this->user->LoadService($service);

		$retriesCount = 1;
		do {

			try {
				$service =  $this->user->GetService($service);
				$selector = new Selector();
				$selector->fields = $fields;
				if (count($predicates)) {
					$selector->predicates = $predicates;
				}
				if ($since && $until) {
					$selector->dateRange = new DateRange($since, $until);
				}
				$result = $service->get($selector);
				return (isset($result->entries) && is_array($result->entries))? $result->entries : array();
			} catch (\Exception $e) {
				if (!strstr($e->getMessage(), 'RateExceededError')) {
					throw new AdWordsException($e->getMessage(), 500, $e);
				}
			}

			sleep(self::BACKOFF_INTERVAL * ($retriesCount + 1));
			$retriesCount++;
		} while ($retriesCount <= self::RETRIES_COUNT);
	}

	/**
	 * Returns accounts managed by current MCC
	 */
	public function getClients()
	{
		return $this->selectorRequest(
			'ManagedCustomerService',
			array('Name', 'CompanyName', 'CustomerId'),
			array(new Predicate('CanManageClients', 'EQUALS', 'false'))
		);
	}

	public function getCampaigns()
	{
		return $this->selectorRequest(
			'CampaignService',
			array('Id', 'Name', 'StartDate', 'EndDate', 'CampaignStatus', 'ServingStatus'),
			array()
		);
	}

	public function getCampaignReport($since, $until)
	{
		return $this->report($since, $until, 'CAMPAIGN_PERFORMANCE_REPORT', array(
			'AverageCpc',
			'AverageCpm',
			'AveragePosition',
			'CampaignId',
			'CampaignName',
			'Clicks',
			'Cost',
			'Date',
			'Impressions',
			'AdNetworkType1'
		));
	}



	public function report($since, $until, $reportType, $fields, $tries=3)
	{
		try {
			$this->user->LoadService('ReportDefinitionService');

			$selector = new Selector();
			$selector->fields = $fields;
			$selector->dateRange = new DateRange($since, $until);

			$definition = new ReportDefinition();
			$definition->reportName = 'Keywords report #'. uniqid();
			$definition->dateRangeType = 'CUSTOM_DATE';
			$definition->reportType = $reportType;
			$definition->downloadFormat = 'XML';
			$definition->selector = $selector;

			$reportFile = $this->temp->createTmpFile();
			try {
				@ReportUtils::DownloadReport($definition, $reportFile, $this->user());

				if (file_exists($reportFile)) {
					$xml = simplexml_load_file($reportFile);
					if (isset($xml->reportDownloadError)) {
						$e = new AdWordsException('DownloadReport Error', 500);
						$e->setData(array(
							'customerId' => $this->user()->GetClientCustomerId(),
							'since' => $since,
							'until' => $until,
							'reportFile' => $reportFile,
							'report' => file_get_contents($reportFile)
						));
						throw $e;
					} else {
						$data = array();
						foreach($xml->table->row as $row) {
							$subData = array();
							foreach($row->attributes() as $k => $v) {
								$subData[$k] = (string)$v;
							}
							if (empty($subData['keywordID'])) {
								$subData['keywordID'] = '';
							}
							if (isset($data[$subData['campaignID']][$subData['keywordID']][$subData['day']])) {
								// merge two different stats for one keyword and day
								$existingData = $data[$subData['campaignID']][$subData['keywordID']][$subData['day']];
								$existingData['avgCPC'] = ($existingData['avgCPC'] + $subData['avgCPC']) / 2;
								$existingData['avgCPM'] = ($existingData['avgCPM'] + $subData['avgCPM']) / 2;
								$existingData['avgPosition'] = (
										(isset($existingData['avgPosition']) ? $existingData['avgPosition'] : 0)
										+ (isset($subData['avgPosition']) ? $subData['avgPosition'] : 0)) / 2;
								$existingData['clicks'] = $existingData['clicks'] + $subData['clicks'];
								$existingData['cost'] = $existingData['cost'] + $subData['cost'];
								$existingData['impressions'] = $existingData['impressions'] + $subData['impressions'];

								$data[$subData['campaignID']][$subData['keywordID']][$subData['day']] = $existingData;
							} else {
								$data[$subData['campaignID']][$subData['keywordID']][$subData['day']] = $subData;
							}
						}
						unlink($reportFile);
						return $data;
					}
				} else {
					$e = new AdWordsException('DownloadReport Error, file not exist', 500);
					$e->setData(array(
						'customerId' => $this->user()->GetClientCustomerId(),
						'since' => $since,
						'until' => $until,
						'reportType' => $reportType,
						'reportFile' => $reportFile
					));
				}

			} catch (ReportDownloadException $e) {
				if ($tries > 0) {
					sleep(30);
					return $this->report($since, $until, $reportType, $fields, $tries-1);
				} else {
					$e = new AdWordsException('DownloadReport Error', 500, $e);
					$e->setData(array(
						'customerId' => $this->user()->GetClientCustomerId(),
						'since' => $since,
						'until' => $until,
						'reportType' => $reportType,
						'reportFile' => $reportFile
					));
					throw $e;
				}
			}
		} catch (\Exception $e) {
			if (strstr($e->getMessage(), 'RateExceededError')) {
				sleep (5 * 60);
				return $this->report($since, $until, $reportType, $fields);
			}

			if ($tries > 0) {
				sleep(30);
				return $this->report($since, $until, $reportType, $fields, $tries-1);
			} else {
				$e = new AdWordsException('DownloadReport Error', 500, $e);
				$e->setData(array(
					'customerId' => $this->user()->GetClientCustomerId(),
					'since' => $since,
					'until' => $until,
					'reportType' => $reportType
				));
				throw $e;
			}
		}

		return array();
	}


	/**
	 * @return AdWordsUser
	 */
	public function user()
	{
		return $this->user;
	}

	public static function normalizeNumber($number)
	{
		return str_replace(',', '', $number);
	}

	/**
	 * Prices are in micros so we need to divide by million
	 */
	public static function normalizePrice($number)
	{
		return self::normalizeNumber($number) / 1000000;
	}

}