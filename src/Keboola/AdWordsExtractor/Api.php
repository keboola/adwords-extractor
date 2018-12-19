<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\Reporting\v201809\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDownloader;
use Google\AdsApi\AdWords\ReportSettings;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\AdWords\v201809\cm\CampaignPage;
use Google\AdsApi\AdWords\v201809\cm\CampaignService;
use Google\AdsApi\AdWords\v201809\cm\DateRange;
use Google\AdsApi\AdWords\v201809\cm\OrderBy;
use Google\AdsApi\AdWords\v201809\cm\Paging;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerPage;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerService;
use Google\Auth\Credentials\UserRefreshCredentials;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SoapClient;
use Symfony\Component\Process\Process;
use Keboola\Temp\Temp;

class Api
{
    const PAGE_LIMIT = 500;

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
    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;
    /**
     * @var GuzzleHttpClientFactory
     */
    private $guzzleClientFactory;

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
        $this->guzzleClient = $this->initGuzzleClient();
        $this->guzzleClientFactory = new GuzzleHttpClientFactory($this->logger);
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
    public function getAllYielded(SoapClient $service, Selector $selector, $pageLimit = self::PAGE_LIMIT)
    {
        $selector->setPaging(new Paging(0, $pageLimit));
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
                    $selector->getPaging()->setStartIndex($selector->getPaging()->getStartIndex() + $pageLimit);
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
    public function getCustomersYielded($since = null, $until = null, $pageLimit = self::PAGE_LIMIT)
    {
        $service = $this->getService($this->session, ManagedCustomerService::class);
        $selector = new Selector();
        $selector->setFields(['Name', 'CustomerId', 'CanManageClients', 'CurrencyCode', 'DateTimeZone']);
        $selector->setOrdering([new OrderBy('CustomerId', 'ASCENDING')]);
        $selector->setPredicates([new Predicate('CanManageClients', 'EQUALS', ['false'])]);
        if ($since && $until) {
            $selector->setDateRange(new DateRange($since, $until));
        }
        return $this->getAllYielded($service, $selector, $pageLimit);
    }

    public function getCampaignsYielded($since = null, $until = null, $pageLimit = self::PAGE_LIMIT)
    {
        $service = $this->getService($this->session, CampaignService::class);
        $selector = new Selector();
        $selector->setFields([
            'Id',
            'Name',
            'Status',
            'ServingStatus',
            'StartDate',
            'EndDate',
            'AdServingOptimizationStatus',
            'AdvertisingChannelType'
        ]);
        $selector->setOrdering([new OrderBy('Id', 'ASCENDING')]);
        if ($since && $until) {
            $selector->setDateRange(new DateRange($since, $until));
        }
        return $this->getAllYielded($service, $selector, $pageLimit);
    }

    public function getService($session, $serviceClass)
    {
        $retry = 0;
        $lastError = null;
        do {
            try {
                return $this->adWordsServices->get($session, $serviceClass);
            } catch (\Exception $e) {
                $lastError = $e;
                $retry++;
                sleep(rand(5, 15));
            }
        } while ($retry < 5);
        throw $lastError ?: new \Exception('AdWords API is failing and backoff with retries did not help.');
    }


    public function getReport($query, $since, $until, $file)
    {
        $query .= sprintf(' DURING %d,%d', $since, $until);
        $isFirstReportInFile = !file_exists($file);
        $reportFile = $this->temp->createTmpFile();

        $reportDownloader = new ReportDownloader($this->session, null, $this->guzzleClient, $this->guzzleClientFactory);
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

    protected function initGuzzleClient()
    {
        $handlerStack = HandlerStack::create();

        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $response && $response->getStatusCode() == 503;
            },
            function ($retries) {
                return rand(60, 600) * 1000;
            }
        ));
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= 5) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        return new \GuzzleHttp\Client(['handler' => $handlerStack]);
    }
}
