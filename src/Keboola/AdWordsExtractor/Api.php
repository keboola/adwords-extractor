<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\Reporting\v201802\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201802\ReportDownloader;
use Google\AdsApi\AdWords\ReportSettings;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\AdWords\v201802\cm\CampaignPage;
use Google\AdsApi\AdWords\v201802\cm\CampaignService;
use Google\AdsApi\AdWords\v201802\cm\DateRange;
use Google\AdsApi\AdWords\v201802\cm\OrderBy;
use Google\AdsApi\AdWords\v201802\cm\Paging;
use Google\AdsApi\AdWords\v201802\cm\Predicate;
use Google\AdsApi\AdWords\v201802\cm\Selector;
use Google\AdsApi\AdWords\v201802\mcm\ManagedCustomerPage;
use Google\AdsApi\AdWords\v201802\mcm\ManagedCustomerService;
use Google\Auth\Credentials\UserRefreshCredentials;
use Monolog\Logger;
use SoapClient;
use Symfony\Component\Process\Process;
use Keboola\Temp\Temp;

class Api
{
    const PAGE_LIMIT = 500;

    /**
     * Number of retries for one API call
     */
    const RETRIES_COUNT = 5;
    /**
     * Back off time before retrying API call
     */
    const BACKOFF_INTERVAL = 60;

    /**
     * @var UserRefreshCredentials
     */
    private $credential;
    private $developerToken;
    /**
     * @var \Google\AdsApi\AdWords\AdWordsSession
     */
    private $session;
    /**
     * @var AdWordsServices
     */
    private $adWordsServices;

    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var Temp
     */
    private $temp;

    public function __construct($clientId, $clientSecret, $developerToken, $refreshToken, Logger $logger)
    {
        $this->credential = new UserRefreshCredentials('https://www.googleapis.com/auth/adwords', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ]);
        $this->developerToken = $developerToken;
        $this->logger = $logger;
        $this->adWordsServices = new AdWordsServices();
    }

    public function setTemp(Temp $temp)
    {
        $this->temp = $temp;
        return $this;
    }

    public function setCustomerId($customerId)
    {
        $reportSettingsBuilder = new ReportSettingsBuilder();
        $reportSettingsBuilder->skipReportHeader(true)->skipReportSummary(true);
        $reportSettings = new ReportSettings($reportSettingsBuilder);
        $this->session = (new AdWordsSessionBuilder())
            ->withDeveloperToken($this->developerToken)
            ->withOAuth2Credential($this->credential)
            ->withClientCustomerId($customerId)
            ->withSoapLogger($this->logger)
            ->withReportDownloaderLogger($this->logger)
            ->withReportSettings($reportSettings)
            ->build();
        return $this;
    }

    /**
     * @param CampaignService|ManagedCustomerService $service
     * @param Selector $selector
     * @return \Generator
     */
    public function getAllYielded(SoapClient $service, Selector $selector)
    {
        $selector->setPaging(new Paging(0, self::PAGE_LIMIT));
        $totalNumEntries = 0;
        do {
            $retry = 0;
            $repeat = true;
            do {
                try {
                    /** @var CampaignPage|ManagedCustomerPage $page */
                    $page = $service->get($selector);
                    if (count($page->getEntries())) {
                        $totalNumEntries = $page->getTotalNumEntries();
                        yield ['entries' => $page->getEntries(), 'total' => $totalNumEntries];
                    }
                    $selector->getPaging()->setStartIndex($selector->getPaging()->getStartIndex() + self::PAGE_LIMIT);
                    $repeat = false;
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'getaddrinfo failed') === false) {
                        throw $e;
                    }
                    $retry++;
                    sleep(rand(5, 15));
                }
            } while ($retry < 5 && $repeat);
        } while ($selector->getPaging()->getStartIndex() < $totalNumEntries);
    }

    /**
     * Returns accounts managed by current MCC
     */
    public function getCustomersYielded($since = null, $until = null)
    {
        $service = $this->adWordsServices->get($this->session, ManagedCustomerService::class);
        $selector = new Selector();
        $selector->setFields(['Name', 'CustomerId', 'CanManageClients', 'CurrencyCode', 'DateTimeZone']);
        $selector->setOrdering([new OrderBy('CustomerId', 'ASCENDING')]);
        $selector->setPredicates([new Predicate('CanManageClients', 'EQUALS', ['false'])]);
        if ($since && $until) {
            $selector->setDateRange(new DateRange($since, $until));
        }
        return $this->getAllYielded($service, $selector);
    }

    public function getCampaignsYielded($since = null, $until = null)
    {
        $service = $this->adWordsServices->get($this->session, CampaignService::class);
        $selector = new Selector();
        $selector->setFields(['Id', 'Name', 'Status', 'ServingStatus', 'StartDate', 'EndDate',
            'AdServingOptimizationStatus', 'AdvertisingChannelType']);
        $selector->setOrdering([new OrderBy('Id', 'ASCENDING')]);
        if ($since && $until) {
            $selector->setDateRange(new DateRange($since, $until));
        }
        return $this->getAllYielded($service, $selector);
    }


    public function getReport($query, $since, $until, $file)
    {
        $query .= sprintf(' DURING %d,%d', $since, $until);
        $isFirstReportInFile = !file_exists($file);

        $reportFile = $this->temp->createTmpFile();
        $reportDownloader = new ReportDownloader(
            $this->session,
            null,
            null,
            new GuzzleHttpClientFactory($this->logger)
        );
        $result = $reportDownloader->downloadReportWithAwql($query, DownloadFormat::CSV);
        $result->saveToFile($reportFile);

        // If first report, include header
        $process = new Process(
            'cat ' . escapeshellarg($reportFile)
            . (!$isFirstReportInFile ? ' | tail -n+2' : '')
            . ' >> ' . escapeshellarg($file)
        );
        $process->setTimeout(null);
        $process->run();
        $output = $process->getOutput();
        $error = $process->getErrorOutput();

        if (!$process->isSuccessful() || $error) {
            throw Exception::reportError(
                'Creating of csv files failed',
                $this->session->getClientCustomerId(),
                $query,
                ['output' => $error ? $error : $output]
            );
        }
    }
}
