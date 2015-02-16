<?php
/**
 * @package adwords-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Extractor;


class ConfigurationStorage extends \Keboola\AdWordsExtractor\Service\ConfigurationStorage
{

    public function getRequiredBucketAttributes()
    {
        return ['developerToken', 'refreshToken', 'customerId'];
    }

    public function getRequiredTableColumns()
    {
        return ['table', 'query'];
    }
}
