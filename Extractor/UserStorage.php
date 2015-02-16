<?php
/**
 * @package adwords-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Extractor;


class UserStorage extends \Keboola\AdWordsExtractor\Service\UserStorage
{
    protected $tables = [
        'customers' => [
            'columns' => ['customerId', 'name', 'companyName', 'canManageClients', 'currencyCode', 'dateTimeZone']
        ],
        'campaigns' => [
            'columns' => ['customerId', 'id', 'name', 'status', 'servingStatus', 'startDate', 'endDate',
                'adServingOptimizationStatus', 'advertisingChannelType', 'displaySelect']
        ]
    ];
}
