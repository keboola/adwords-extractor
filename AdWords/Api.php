<?php
namespace Keboola\AdWordsExtractorBundle\AdWords;

use AdWordsConstants;
use AdWordsUser;
use DateRange;
use ErrorUtils;
use Keboola\Csv\CsvFile;
use Keboola\ExtractorBundle\Common\Logger;
use Paging;
use Predicate;
use ReportDownloadException;
use ReportUtils;
use Selector;
use Symfony\Component\Process\Process;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use Syrup\ComponentBundle\Exception\UserException;
use Syrup\ComponentBundle\Filesystem\Temp;

class AdWordsException extends SyrupComponentException
{
	public function __construct($message, $code=500, $previous=null)
	{
		parent::__construct($code, $message, $previous);
	}
}

class Api
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
	private $reportFiles = array();

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
			throw new UserException($e->getMessage(), $e);
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

		$retriesCount = 0;
		do {
			sleep(self::BACKOFF_INTERVAL * ($retriesCount + 1));
			$retriesCount++;

			try {
				$serviceClass =  $this->user->GetService($service);
				$selector = new Selector();
				$selector->fields = $fields;
				if (count($predicates)) {
					$selector->predicates = $predicates;
				}
				if ($since && $until) {
					$selector->dateRange = new DateRange($since, $until);
				}

				$result = array();
				$selector->paging = new Paging(0, AdWordsConstants::RECOMMENDED_PAGE_SIZE);
				do {
					$page = $serviceClass->get($selector);

					if (isset($page->entries)) {
						$result = array_merge($result, $page->entries);
					}

					$selector->paging->startIndex += AdWordsConstants::RECOMMENDED_PAGE_SIZE;
				} while ($page->totalNumEntries > $selector->paging->startIndex);

				return $result;
			} catch (\SoapFault $fault) {
				$soapErrors = array();
				foreach (ErrorUtils::GetApiErrors($fault) as $error) {
					$soapErrors[] = array(
						'apiErrorType' => $error->ApiErrorType,
						'externalPolicyName' => $error->externalPolicyName
					);
				}

				if ($retriesCount <= self::RETRIES_COUNT) {
					Logger::log(\Monolog\Logger::ERROR, 'API Error', array(
						'service' => $service,
						'fields' => $fields,
						'predicates' => $predicates,
						'since' => $since,
						'until' => $until,
						'exception' => $fault->getMessage(),
						'code' => $fault->getCode(),
						'soapErrors' => $soapErrors,
						'retry' => $retriesCount
					));
				} else {
					throw new UserException($fault->getMessage(), $fault);
				}
				//if (!strstr($e->getMessage(), 'RateExceededError')) {
				//}
			}
		} while ($retriesCount <= self::RETRIES_COUNT);
	}

	/**
	 * Returns accounts managed by current MCC
	 */
	public function getCustomers($since=null, $until=null)
	{
		return $this->selectorRequest(
			'ManagedCustomerService',
			array('Name', 'CompanyName', 'CustomerId', 'CanManageClients', 'CurrencyCode', 'DateTimeZone'),
			array(new Predicate('CanManageClients', 'EQUALS', 'false')),
			$since,
			$until
		);
	}

	public function getCampaigns($since=null, $until=null)
	{
		return $this->selectorRequest(
			'CampaignService',
			array('Id', 'Name', 'Status', 'ServingStatus', 'StartDate', 'EndDate', 'AdServingOptimizationStatus',
				'AdvertisingChannelType'),
			array(),
			$since,
			$until
		);
	}


	public function getReport($query, $since, $until, $file)
	{
		$query .= sprintf(' DURING %d,%d', $since, $until);
		$isFirstReportInFile = !isset($this->reportFiles[$file]);

		try {
			$reportFile = $this->temp->createTmpFile();
			ReportUtils::DownloadReportWithAwql($query, $reportFile, $this->user, 'CSV', array(
				'skipReportHeader' => true,
				'skipReportSummary' => true
			));

			if (!file_exists($reportFile)) {
				$e = new AdWordsException('DownloadReport Error, csv file does not exist');
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFile
				));
			}


			// Do not save empty reports (with one line only)
			$process = new Process('wc -l ' . escapeshellarg($reportFile) . ' | awk \'{print $1}\'');
			$process->run();
			$linesCount = $process->getOutput();
			$error = $process->getErrorOutput();

			if (!$process->isSuccessful() || $error) {
				$e = new AdWordsException('DownloadReport count lines Error');
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query,
					'reportFile' => $reportFile,
					'output' => $error? $error : $linesCount
				));
				throw $e;
			}
			if ($linesCount > 1 || $isFirstReportInFile) {
				if (!isset($this->reportFiles[$file])) {
					$this->reportFiles[$file] = $this->temp->createTmpFile();
				}

				// If first report, include header
				$process = new Process('cat ' . escapeshellarg($reportFile) . (!$isFirstReportInFile ? ' | tail -n+2' : '')
					. ' >> ' . escapeshellarg($this->reportFiles[$file]));
				$process->setTimeout(5 * 60 * 60);
				$process->run();
				$output = $process->getOutput();
				$error = $process->getErrorOutput();

				if (!$process->isSuccessful() || $error) {
					$e = new AdWordsException('DownloadReport Error');
					$e->setData(array(
						'customerId' => $this->user->GetClientCustomerId(),
						'query' => $query,
						'reportFile' => $reportFile,
						'output' => $error ? $error : $output
					));
					throw $e;
				}

			}

		} catch (ReportDownloadException $e) {
			throw new UserException($e->getMessage(), $e);
		} catch (\Exception $e) {
			if (strstr($e->getMessage(), 'RateExceededError')) {
				sleep (5 * 60);
				return $this->getReport($query, $since, $until, $file);
			} else {
				$e = new AdWordsException('DownloadReport Error. ' . $e->getMessage(), 400, $e);
				$e->setData(array(
					'customerId' => $this->user->GetClientCustomerId(),
					'query' => $query
				));
				throw $e;
			}
		}
	}

	public function getReportFiles()
	{
		return $this->reportFiles;
	}

}