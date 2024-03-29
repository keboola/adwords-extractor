<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Generator;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\Reporting\v201809\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDownloader;
use Google\AdsApi\AdWords\ReportSettings;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\AdsApi\AdWords\v201809\cm\CampaignPage;
use Google\AdsApi\AdWords\v201809\cm\CampaignService;
use Google\AdsApi\AdWords\v201809\cm\DateRange;
use Google\AdsApi\AdWords\v201809\cm\OrderBy;
use Google\AdsApi\AdWords\v201809\cm\Paging;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerPage;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerService;
use Google\AdsApi\Common\AdsSession;
use Google\AdsApi\Common\AdsSoapClient;
use Google\Auth\Credentials\UserRefreshCredentials;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use SoapClient;
use Symfony\Component\Process\Process;
use Keboola\Temp\Temp;
use Throwable;

class Api
{
    protected const PAGE_LIMIT = 500;

    private const DEFAULT_MAX_ATTEMPTS = 5;

    /**
     * @var UserRefreshCredentials
     */
    private $credential;
    /** @var string  */
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
     * @var LoggerInterface
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

    public function __construct(string $developerToken, LoggerInterface $logger)
    {
        $this->developerToken = $developerToken;
        $this->logger = $logger;
        $this->adWordsServices = new AdWordsServices();
        $this->guzzleClientFactory = new GuzzleHttpClientFactory($this->logger);
        $this->guzzleClient = $this->guzzleClientFactory->generateHttpClient();
    }

    public function setOAuthCredentials(string $clientId, string $clientSecret, string $refreshToken): Api
    {
        $this->credential = new UserRefreshCredentials('https://www.googleapis.com/auth/adwords', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);
        return $this;
    }

    public function setTemp(Temp $temp): Api
    {
        $this->temp = $temp;
        return $this;
    }

    public function setCustomerId(string $customerId): Api
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

    public function getAllYielded(
        SoapClient $service,
        Selector $selector,
        ?int $pageLimit = self::PAGE_LIMIT
    ): Generator {
        $selector->setPaging(new Paging(0, $pageLimit));
        $totalNumEntries = 0;
        $retryProxy = new RetryProxy(
            new SimpleRetryPolicy(self::DEFAULT_MAX_ATTEMPTS, [RetryException::class]),
            new ExponentialBackOffPolicy(30000, 1.1, 45000)
        );
        do {
            $result = $retryProxy->call(function () use ($service, $selector, $pageLimit): ?array {
                try {
                    /** @var CampaignPage|ManagedCustomerPage $page */
                    $page = $service->get($selector);
                    $selector
                        ->getPaging()
                        ->setStartIndex($selector->getPaging()->getStartIndex() + $pageLimit)
                    ;
                    if (count((array) $page->getEntries())) {
                        return [
                            'entries' => $page->getEntries(),
                            'total' => $page->getTotalNumEntries(),
                        ];
                    }
                    return null;
                } catch (Throwable $e) {
                    if (strpos($e->getMessage(), 'getaddrinfo failed') !== false
                        || strpos($e->getMessage(), 'currently unavailable') !== false
                        || strpos($e->getMessage(), 'UNEXPECTED_INTERNAL_API_ERROR') !== false
                    ) {
                        throw new RetryException($e->getMessage(), $e->getCode(), $e);
                    }
                    throw $e;
                }
            });
            if ($result) {
                $totalNumEntries = $result['total'];
                yield $result;
            }
        } while ($selector->getPaging()->getStartIndex() < $totalNumEntries);
    }

    public function getCustomersYielded(
        ?string $since = null,
        ?string $until = null,
        ?int $pageLimit = self::PAGE_LIMIT
    ): Generator {
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

    public function getCampaignsYielded(
        ?string $since = null,
        ?string $until = null,
        ?int $pageLimit = self::PAGE_LIMIT
    ): Generator {
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
            'AdvertisingChannelType',
        ]);
        $selector->setOrdering([new OrderBy('Id', 'ASCENDING')]);
        if ($since && $until) {
            $selector->setDateRange(new DateRange($since, $until));
        }
        return $this->getAllYielded($service, $selector, $pageLimit);
    }

    public function getService(AdsSession $session, string $serviceClass): AdsSoapClient
    {
        $retry = 0;
        do {
            try {
                return $this->adWordsServices->get($session, $serviceClass);
            } catch (Throwable $e) {
                $lastError = $e;
                $retry++;
                sleep(rand(5, 15));
            }
        } while ($retry < 5);
        throw $lastError;
    }


    public function getReport(string $query, ?string $since, ?string $until, string $file): void
    {
        if ($since && $until) {
            $query .= sprintf(' DURING %d,%d', $since, $until);
        }
        $isFirstReportInFile = !file_exists($file);
        $reportFile = $this->temp->createTmpFile();
        $reportFilePath = (string) $reportFile->getRealPath();

        $retryProxy = new RetryProxy(
            new SimpleRetryPolicy(
                self::DEFAULT_MAX_ATTEMPTS,
                [
                    RequestException::class,
                    ApiException::class,
                ]
            ),
            new ExponentialBackOffPolicy(),
            $this->logger
        );

        $retryProxy->call(function () use ($query, $reportFilePath): void {
            $reportDownloader = new ReportDownloader(
                $this->session,
                null,
                $this->guzzleClient,
                $this->guzzleClientFactory
            );
            $result = $reportDownloader->downloadReportWithAwql($query, DownloadFormat::CSV);
            $result->saveToFile($reportFilePath);
        });

        // If first report, include header
        $process = Process::fromShellCommandline(
            'cat ' . escapeshellarg($reportFilePath)
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
                (string) $this->session->getClientCustomerId(),
                $query,
                ['output' => $error ? $error : $output]
            );
        }
    }
}
