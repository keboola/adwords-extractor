<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Keboola\Csv\CsvFile;
use Keboola\Temp\Temp;
use ReportDownloadException;
use Symfony\Component\Yaml\Yaml;

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
                'adServingOptimizationStatus', 'advertisingChannelType', 'displaySelect']
        ]
    ];

    /** @var UserStorage */
    protected $userStorage;

    /** @var  Api */
    protected $api;

    public function __construct($clientId, $clientSecret, $developerToken, $refreshToken, $customerId, $folder, $bucket)
    {
        $this->api = new Api(
            $clientId,
            $clientSecret,
            $developerToken,
            $refreshToken
        );
        $this->api
            ->setCustomerId($customerId)
            ->setUserAgent('keboola-adwords-extractor')
            ->setTemp(new Temp());
        $this->userStorage = new UserStorage(self::$userTables, $folder, $bucket);
    }

    public function extract(array $queries, $since, $until)
    {
        $customers = $this->api->getCustomers($since, $until);

        foreach ($customers as $customer) {
            $this->userStorage->save('customers', $customer);

            $this->api->setCustomerId($customer->customerId);
            foreach ($queries as $query) {
                try {
                    $fileName = $this->userStorage->getReportFilename($query['name']);
                    $this->api->getReport($query['query'], $since, $until, $fileName);
                    $this->userStorage->createManifest(
                        $fileName,
                        $query['name'],
                        isset($query['primary']) ? $query['primary'] : []
                    );
                } catch (ReportDownloadException $e) {
                    throw new Exception("Getting report for client '{$customer->name}' failed:{$e->getMessage()}", $e);
                }
            }
        }
    }
}
