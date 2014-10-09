<?
namespace Keboola\AdWordsExtractorBundle\Extractor;

use AdWordsUser;
use DateRange;
use Keboola\Csv\CsvFile;
use Predicate;
use ReportDefinition;
use ReportDownloadException;
use ReportUtils;
use Selector;
use Symfony\Component\Process\Process;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use Syrup\ComponentBundle\Exception\UserException;
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

	public static function getUser($clientId, $clientSecret, $developerToken)
	{
		$user = new AdWordsUser();
		$user->SetDeveloperToken($developerToken);
		$user->SetOAuth2Info(array(
			'client_id' => $clientId,
			'client_secret' => $clientSecret
		));
		return $user;
	}

	public static function getOAuthUrl($clientId, $clientSecret, $developerToken, $redirectUri)
	{
		$user = self::getUser($clientId, $clientSecret, $developerToken);
		$OAuth2Handler = $user->GetOAuth2Handler();
		return $OAuth2Handler->GetAuthorizationUrl($user->GetOAuth2Info(), $redirectUri, true, array(
			'approval_prompt' => 'force'
		));
	}

	public static function getRefreshToken($clientId, $clientSecret, $developerToken, $code, $redirectUri)
	{
		$user = self::getUser($clientId, $clientSecret, $developerToken);
		$OAuth2Handler = $user->GetOAuth2Handler();
		$t = $OAuth2Handler->GetAccessToken($user->GetOAuth2Info(), $code, $redirectUri);

		return isset($t['refresh_token'])? $t['refresh_token'] : false;
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
	public function getCustomers()
	{
		return $this->selectorRequest(
			'ManagedCustomerService',
			array('Name', 'Login', 'CompanyName', 'CustomerId', 'CanManageClients', 'CurrencyCode', 'DateTimeZone'),
			array(new Predicate('CanManageClients', 'EQUALS', 'false'))
		);
	}

	public function getCampaigns($since=null, $until=null)
	{
		return $this->selectorRequest(
			'CampaignService',
			array('Id', 'Name', 'CampaignStatus', 'ServingStatus', 'StartDate', 'EndDate', 'AdServingOptimizationStatus',
				'AdvertisingChannelType', 'DisplaySelect', 'TrackingUrlTemplate'),
			array(),
			$since,
			$until
		);
	}


	public function getReport($query, $since, $until)
	{
		$query .= sprintf(' DURING %d,%d', $since, $until);

		try {
			$reportFileGz = $this->temp->createTmpFile();
			$reportFile = $this->temp->createTmpFile();
			ReportUtils::DownloadReportWithAwql($query, $reportFileGz, $this->user, 'GZIPPED_CSV');

			$process = new Process('cat ' . escapeshellarg($reportFileGz) . ' | gzip -d | tail -n+2 | head -n-1 > '
				. escapeshellarg($reportFile));
			$process->setTimeout(5 * 60 * 60);
			$process->run();
			$output = $process->getOutput();
			$error = $process->getErrorOutput();

			if (!$process->isSuccessful() || $error) {
				$e = new AdWordsException('DownloadReport gzip Error', 500);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFileGz,
					'output' => $error? $error : $output
				));
				throw $e;
			}

			if (!file_exists($reportFileGz)) {
				$e = new AdWordsException('DownloadReport Error, gzip file does not exist', 500);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFileGz
				));
			}

			if (!file_exists($reportFile)) {
				$e = new AdWordsException('DownloadReport Error, csv file does not exist', 500);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFile
				));
			}

			// Do not save empty reports (with one line only)
			$process = new Process('wc -l ' . escapeshellarg($reportFile) . ' | awk \'{print $1}\'');
			$process->run();
			$output = $process->getOutput();
			$error = $process->getErrorOutput();

			if (!$process->isSuccessful() || $error) {
				$e = new AdWordsException('DownloadReport count lines Error', 500);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFileGz,
					'output' => $error? $error : $output
				));
				throw $e;
			}

			return ($output > 1)? new CsvFile($reportFile) : false;

		} catch (ReportDownloadException $e) {
			throw new UserException($e->getMessage(), $e);
		} catch (\Exception $e) {
			if (strstr($e->getMessage(), 'RateExceededError')) {
				sleep (5 * 60);
				return $this->getReport($query, $since, $until);
			} else {
				$e = new AdWordsException('DownloadReport Error. ' . $e->getMessage(), 500, $e);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query
				));
				throw $e;
			}
		}
	}

}