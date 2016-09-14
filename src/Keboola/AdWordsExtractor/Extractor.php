<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;
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

    private $logger;

    public function __construct($clientId, $clientSecret, $developerToken, $refreshToken, $customerId, $folder, $bucket, LoggerInterface $logger)
    {
        $this->api = new Api(
            $clientId,
            $clientSecret,
            $developerToken,
            $refreshToken,
            $logger
        );
        $this->api
            ->setCustomerId($customerId)
            ->setUserAgent('keboola-adwords-extractor')
            ->setTemp(new Temp());
        $this->userStorage = new UserStorage(self::$userTables, $folder, $bucket);
        $this->logger = $logger;
    }

    public function extract(array $queries, $since, $until)
    {
        $customers = array_values($this->api->getCustomers($since, $until));
        $customersCount = count($customers);

        foreach ($customers as $i => $customer) {
            $this->logger->info(sprintf(sprintf('Extraction - Customer %d/%d  [%-30s] start', $i + 1, $customersCount, $customer->name)));
            $this->userStorage->save('customers', $customer);
            $this->api->setCustomerId($customer->customerId);

            foreach ($this->api->getCampaigns($since, $until) as $campaign) {
                $campaign = (array)$campaign;
                $campaign['customerId'] = $customer->customerId;
                $this->userStorage->save('campaigns', $campaign);
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
                } catch (ReportDownloadException $e) {
                    throw new Exception(
                        "Getting report for client '{$customer->name}' failed:{$e->getMessage()}",
                        400,
                        $e
                    );
                }
            }
        }
    }
}
