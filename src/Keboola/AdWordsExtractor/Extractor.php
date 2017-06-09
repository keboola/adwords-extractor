<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\v201705\cm\ApiException;
use Keboola\Temp\Temp;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class Extractor
{
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

    /** @var  Logger */
    private $logger;

    public function __construct($options)
    {
        $required = ['oauthKey', 'oauthSecret', 'refreshToken', 'developerToken', 'customerId', 'outputPath', 'logger'];
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
        $this->logger = $options['logger'];
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
        $current = 0;
        foreach ($this->api->getCustomersYielded($since, $until) as $result) {
            foreach ($result['entries'] as $customer) {
                $parsedCustomer = $this->parseApiResult($customer);
                $current++;
                $this->logger->info(sprintf(
                    'Extraction of data for customer %s (%d/%d) started',
                    $parsedCustomer['name'],
                    $current,
                    $result['total']
                ));
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
                            $this->logger->error("Getting report for client '{$parsedCustomer['name']}' failed:{$e->getMessage()}");
                        }
                    }
                } catch (ApiException $e) {
                    $this->logger->error("Getting data for client '{$parsedCustomer['name']}' failed:{$e->getMessage()}");
                }
            }
        }
    }
}
