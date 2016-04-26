<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

require_once 'Google/Api/Ads/Common/Util/ErrorUtils.php';

use Symfony\Component\Process\Process;
use Keboola\Temp\Temp;

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
     * @var \AdWordsUser
     */
    private $user;
    /**
     * @var Temp
     */
    private $temp;

    public function __construct($clientId, $clientSecret, $developerToken, $refreshToken)
    {
        $this->user = new \AdWordsUser();
        $this->user->SetDeveloperToken($developerToken);
        $this->user->SetOAuth2Info([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ]);
        try {
            $handler = $this->user->GetOAuth2Handler();
            $credentials = $handler->RefreshAccessToken($this->user->GetOAuth2Info());
            $this->user->SetOAuth2Info($credentials);
        } catch (\Exception $e) {
            throw new Exception("OAuth Error: " . $e->getMessage(), 400, $e);
        }
    }

    public function setUserAgent($agent)
    {
        $this->user->SetUserAgent($agent);
        return $this;
    }

    public function setTemp(Temp $temp)
    {
        $this->temp = $temp;
        return $this;
    }

    public function setCustomerId($customerId)
    {
        $this->user->SetClientCustomerId($customerId);
        return $this;
    }

    public static function getUser($clientId, $clientSecret, $developerToken)
    {
        $user = new \AdWordsUser();
        $user->SetDeveloperToken($developerToken);
        $user->SetOAuth2Info([
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]);
        return $user;
    }

    public static function getOAuthUrl($clientId, $clientSecret, $developerToken, $redirectUri)
    {
        $user = self::getUser($clientId, $clientSecret, $developerToken);
        $OAuth2Handler = $user->GetOAuth2Handler();
        return $OAuth2Handler->GetAuthorizationUrl($user->GetOAuth2Info(), $redirectUri, true, [
            'approval_prompt' => 'force'
        ]);
    }

    public static function getRefreshToken($clientId, $clientSecret, $developerToken, $code, $redirectUri)
    {
        $user = self::getUser($clientId, $clientSecret, $developerToken);
        $OAuth2Handler = $user->GetOAuth2Handler();
        $t = $OAuth2Handler->GetAccessToken($user->GetOAuth2Info(), $code, $redirectUri);

        return isset($t['refresh_token'])? $t['refresh_token'] : false;
    }

    public function selectorRequest($service, $fields, $predicates = [], $since = null, $until = null)
    {
        $this->user->LoadService($service);

        $retriesCount = 0;
        do {
            sleep(self::BACKOFF_INTERVAL * $retriesCount);
            $retriesCount++;

            try {
                $serviceClass =  $this->user->GetService($service);
                $selector = new \Selector();
                $selector->fields = $fields;
                if (count($predicates)) {
                    $selector->predicates = $predicates;
                }
                if ($since && $until) {
                    $selector->dateRange = new \DateRange($since, $until);
                }

                $result = [];
                $selector->paging = new \Paging(0, \AdWordsConstants::RECOMMENDED_PAGE_SIZE);
                do {
                    $page = $serviceClass->get($selector);
                    if (isset($page->entries)) {
                        $result = array_merge($result, $page->entries);
                    }

                    $selector->paging->startIndex += \AdWordsConstants::RECOMMENDED_PAGE_SIZE;
                } while ($page->totalNumEntries > $selector->paging->startIndex);

                return $result;
            } catch (\SoapFault $fault) {
                $soapErrors = [];
                foreach (\ErrorUtils::GetApiErrors($fault) as $error) {
                    if (in_array($error->reason, ['DEVELOPER_TOKEN_NOT_APPROVED'])) {
                        throw new Exception($error->reason, $fault->getCode(), $fault);
                    }
                    if (property_exists($error, 'ApiErrorType') && $error->ApiErrorType == 'AuthorizationError') {
                        throw new Exception(
                            'Authorization Error, your refresh token is probably not valid. Check if you still have '
                                . 'access to the service.',
                            $fault->getCode(),
                            $fault
                        );
                    }
                    $soapErrors[] = [
                        'reason' => $error->reason,
                        'apiErrorType' => $error->ApiErrorType,
                        'externalPolicyName' => $error->externalPolicyName
                    ];
                }

                if ($retriesCount > self::RETRIES_COUNT) {
                    throw new Exception($fault->getMessage(), $fault->getCode(), $fault);
                }
            }
        } while ($retriesCount <= self::RETRIES_COUNT);
    }

    /**
     * Returns accounts managed by current MCC
     */
    public function getCustomers($since = null, $until = null)
    {
        return $this->selectorRequest(
            'ManagedCustomerService',
            ['Name', 'CompanyName', 'CustomerId', 'CanManageClients', 'CurrencyCode', 'DateTimeZone'],
            [new \Predicate('CanManageClients', 'EQUALS', 'false')],
            $since,
            $until
        );
    }

    public function getCampaigns($since = null, $until = null)
    {
        return $this->selectorRequest(
            'CampaignService',
            ['Id', 'Name', 'Status', 'ServingStatus', 'StartDate', 'EndDate', 'AdServingOptimizationStatus',
                'AdvertisingChannelType'],
            [],
            $since,
            $until
        );
    }


    public function getReport($query, $since, $until, $file, $retries = 10)
    {
        $query .= sprintf(' DURING %d,%d', $since, $until);
        $isFirstReportInFile = !file_exists($file);

        try {
            $reportFile = $this->temp->createTmpFile();
            \ReportUtils::DownloadReportWithAwql($query, $reportFile, $this->user, 'CSV', [
                'skipReportHeader' => true,
                'skipReportSummary' => true
            ]);

            if (!file_exists($reportFile)) {
                throw Exception::reportError(
                    'Csv file was not created',
                    $this->user->GetClientCustomerId(),
                    $query
                );
            }


            // Do not save empty reports (with one line only)
            $process = new Process('wc -l ' . escapeshellarg($reportFile) . ' | awk \'{print $1}\'');
            $process->run();
            $linesCount = $process->getOutput();
            $error = $process->getErrorOutput();

            if (!$process->isSuccessful() || $error) {
                throw Exception::reportError(
                    'Counting csv file lines failed',
                    $this->user->GetClientCustomerId(),
                    $query,
                    ['output' => $error ? $error : $linesCount]
                );
            }
            if ($linesCount > 1 || $isFirstReportInFile) {
                // If first report, include header
                $process = new Process(
                    'cat ' . escapeshellarg($reportFile)
                    . (!$isFirstReportInFile ? ' | tail -n+2' : '')
                    . ' >> ' . escapeshellarg($file)
                );
                $process->setTimeout(5 * 60 * 60);
                $process->run();
                $output = $process->getOutput();
                $error = $process->getErrorOutput();

                if (!$process->isSuccessful() || $error) {
                    throw Exception::reportError(
                        'Concatenating csv files failed',
                        $this->user->GetClientCustomerId(),
                        $query,
                        ['output' => $error ? $error : $output]
                    );
                }
            }
        } catch (\ReportDownloadException $e) {
            if (strstr($e->getMessage(), 'ERROR_GETTING_RESPONSE_FROM_BACKEND') !== false && $retries > 0) {
                sleep(rand(5, 60));
                $this->getReport($query, $since, $until, $file, $retries-1);
            } else {
                throw $e;
            }
        } catch (\Exception $e) {
            if (strstr($e->getMessage(), 'RateExceededError') !== false) {
                sleep(5 * 60);
                $this->getReport($query, $since, $until, $file);
            } else {
                throw Exception::reportError(
                    $e->getMessage(),
                    $this->user->GetClientCustomerId(),
                    $query
                );
            }
        }
    }
}
