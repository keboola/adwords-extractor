<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Keboola\Temp\Temp;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\ConsoleOutput;

class Extractor
{

    const RESERVED_TABLE_NAMES = [
        'customers',
        'campaigns',
    ];

    protected static $userTables = [
        'customers' => [
            'primary' => ['customerId'],
            'columns' => ['customerId', 'name', 'companyName', 'canManageClients', 'currencyCode', 'dateTimeZone']
        ],
        'campaigns' => [
            'primary' => ['customerId', 'id'],
            'columns' => ['customerId', 'id', 'name', 'status', 'servingStatus', 'startDate', 'endDate',
                'adServingOptimizationStatus', 'advertisingChannelType']
        ]
    ];

    /** @var UserStorage */
    protected $userStorage;

    /** @var  Api */
    protected $api;

    /** @var  ConsoleOutput */
    private $output;

    public function __construct($options)
    {
        $required = ['oauthKey', 'oauthSecret', 'refreshToken', 'developerToken', 'customerId', 'outputPath', 'output'];
        foreach ($required as $item) {
            if (!isset($options[$item])) {
                throw new \Exception("Option $item is not set");
            }
        }

        $this->api = new Api(
            $options['oauthKey'],
            $options['oauthSecret'],
            $options['developerToken'],
            $options['refreshToken'],
            new Logger('adwords-api', [new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING)])
        );
        $this->api
            ->setCustomerId($options['customerId'])
            ->setTemp(new Temp());
        $configId = getenv('KBC_CONFIGID') ? getenv('KBC_CONFIGID') : 'default';
        $bucket = isset($options['bucket']) ? $options['bucket'] : UserStorage::getDefaultBucket($configId);
        $this->userStorage = new UserStorage(self::$userTables, $options['outputPath'], $bucket);
        $this->output = $options['output'];
    }

    protected function parseApiResult($in)
    {
        $out = (array)$in;
        foreach ($out as $key => $val) {
            if ($key[0] === "\0") {
                $out[substr($key, 3)] = $val;
                unset($out[$key]);
            }
        }
        return $out;
    }

    public function extract(array $queries, $since, $until)
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
                $parsedCustomer = $this->parseApiResult($customer);
                $current++;
                $this->output->writeln("Extraction of data for customer {$parsedCustomer['name']} ({$current}/{$result['total']}) started");
                $this->userStorage->save('customers', $parsedCustomer);
                $this->api->setCustomerId($parsedCustomer['customerId']);

                try {
                    $this->userStorage->save('campaigns', []);
                    foreach ($this->api->getCampaignsYielded($since, $until) as $campaigns) {
                        foreach ($campaigns['entries'] as $campaign) {
                            $parsedCampaign = $this->parseApiResult($campaign);
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
                            $this->output->getErrorOutput()->writeln("Getting report for client '{$parsedCustomer['name']}' failed:{$e->getMessage()}");
                            $anyQueryFailed = true;
                        }
                    }
                } catch (ApiException $e) {
                    $this->output->getErrorOutput()->writeln("Getting data for client '{$parsedCustomer['name']}' failed:{$e->getMessage()}");
                    $anyQueryFailed = true;
                }
            }
        }

        if ($anyQueryFailed) {
            throw new Exception('Failed to get results for some queries, please check the log');
        }
    }
}
