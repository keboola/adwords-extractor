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

    public function extract(array $reports, $since, $until)
    {
        $customers = $this->api->getCustomers($since, $until);

        foreach ($customers as $customer) {
            $this->userStorage->save('customers', $customer);

            $this->api->setCustomerId($customer->customerId);
            foreach ($this->api->getCampaigns($since, $until) as $campaign) {
                $campaign->customerId = $customer->customerId;
                $this->userStorage->save('campaigns', $campaign);
            }

            foreach ($reports as $report) {
                try {
                    $this->api->getReport(
                        $report['query'],
                        $since,
                        $until,
                        $this->userStorage->getReportFilename($report['name'])
                    );
                } catch (ReportDownloadException $e) {
                    throw new Exception("Getting report for client '{$customer->name}' failed:{$e->getMessage()}", $e);
                }
            }
        }
    }
}
