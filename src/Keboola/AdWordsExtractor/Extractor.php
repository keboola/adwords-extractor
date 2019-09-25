<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Keboola\Temp\Temp;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Extractor
{

    protected const RESERVED_TABLE_NAMES = [
        'customers',
        'campaigns',
    ];

    /** @var array  */
    protected static $userTables = [
        'customers' => [
            'primary' => ['customerId'],
            'columns' => ['customerId', 'name', 'companyName', 'canManageClients', 'currencyCode', 'dateTimeZone'],
        ],
        'campaigns' => [
            'primary' => ['customerId', 'id'],
            'columns' => ['customerId', 'id', 'name', 'status', 'servingStatus', 'startDate', 'endDate',
                'adServingOptimizationStatus', 'advertisingChannelType'],
        ],
    ];

    /** @var LoggerInterface  */
    protected $logger;

    /** @var UserStorage */
    protected $userStorage;

    /** @var  Api */
    protected $api;

    public function __construct(Config $config, LoggerInterface $logger, string $folder)
    {
        $this->logger = $logger;
        $apiLogHandler = \Keboola\Component\Logger::getDefaultLogHandler();
        $apiLogHandler->setLevel(Logger::ERROR);

        $this->api = new Api($config->getDeveloperToken(), new Logger('api', [$apiLogHandler]));
        $this->api
            ->setOAuthCredentials(
                $config->getOAuthApiAppKey(),
                $config->getOAuthApiAppSecret(),
                $config->getRefreshToken()
            )
            ->setCustomerId($config->getCustomerId())
            ->setTemp(new Temp());
        $configId = getenv('KBC_CONFIGID') ? (string) getenv('KBC_CONFIGID') : 'default';
        $bucket = !empty($config->getBucket()) ? $config->getBucket() : UserStorage::getDefaultBucket($configId);
        $this->userStorage = new UserStorage(self::$userTables, $folder, $bucket);
    }

    protected function parseApiResult(array $in): array
    {
        $out = $in;
        foreach ($out as $key => $val) {
            if ($key[0] === "\0") {
                $out[substr($key, 3)] = $val;
                unset($out[$key]);
            }
        }
        return $out;
    }

    public function extract(array $queries, string $since, string $until): void
    {
        foreach ($queries as $query) {
            if (in_array($query['name'], self::RESERVED_TABLE_NAMES)) {
                throw new Exception(sprintf(
                    '"%s" is reserved table name (%s) that cannot be used for query result',
                    $query['name'],
                    implode(', ', self::RESERVED_TABLE_NAMES)
                ));
            }
        }
        $current = 0;
        $anyQueryFailed = false;
        foreach ($this->api->getCustomersYielded($since, $until) as $result) {
            foreach ($result['entries'] as $customer) {
                $parsedCustomer = $this->parseApiResult((array) $customer);
                $current++;
                $this->logger->info('Extraction of data for customer '
                    . "{$parsedCustomer['name']} ({$current}/{$result['total']}) started");
                $this->userStorage->save('customers', $parsedCustomer);
                $this->api->setCustomerId((string) $parsedCustomer['customerId']);

                try {
                    $this->userStorage->save('campaigns', []);
                    foreach ($this->api->getCampaignsYielded($since, $until) as $campaigns) {
                        foreach ($campaigns['entries'] as $campaign) {
                            $parsedCampaign = $this->parseApiResult((array) $campaign);
                            $parsedCampaign['customerId'] = $parsedCustomer['customerId'];
                            $this->userStorage->save('campaigns', $parsedCampaign);
                        }
                    }

                    foreach ($queries as $query) {
                        try {
                            $fileName = $this->userStorage->getReportFilename($query['name']);
                            $this->api->getReport($query['query'], $since, $until, $fileName);
                            $this->userStorage->createManifest(
                                $fileName,
                                $query['name'],
                                isset($query['primary']) ? $query['primary'] : []
                            );
                        } catch (ApiException $e) {
                            $this->logger->error('Getting report for client '
                                . "'{$parsedCustomer['name']}' failed: {$e->getMessage()}");
                            $anyQueryFailed = true;
                        }
                    }
                } catch (ApiException $e) {
                    $this->logger->error("Getting data for client '{$parsedCustomer['name']}' "
                        . "failed: {$e->getMessage()}");
                    $anyQueryFailed = true;
                }
            }
        }

        if ($anyQueryFailed) {
            throw new Exception('Failed to get results for some queries, please check the log');
        }
    }
}
