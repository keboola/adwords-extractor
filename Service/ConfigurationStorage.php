<?php
/**
 * @package adwords-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Service;

use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Client;
use Keboola\Syrup\Exception\UserException;

abstract class ConfigurationStorage
{
    /**
     * @var \Keboola\StorageApi\Client
     */
    protected $storageApiClient;
    protected $appName;

    public function __construct($appName, Client $storageApi)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApi;
    }


    /**
     * Return list of required attributes of configuration bucket
     * @return array
     */
    abstract public function getRequiredBucketAttributes();

    /**
     * Return list of required columns of configuration table
     * @return array
     */
    abstract public function getRequiredTableColumns();

    
    public function getBucketId()
    {
        return 'sys.c-' . $this->appName;
    }

    public function getConfigurationsList()
    {
        if (!$this->storageApiClient->bucketExists($this->getBucketId())) {
            throw new UserException(sprintf('Configuration bucket %s does not exist', $this->getBucketId()));
        }
        $result = [];
        foreach ($this->storageApiClient->listTables($this->getBucketId()) as $table) {
            $result[] = $table['name'];
        }
        return $result;
    }

    public function getConfiguration($config)
    {
        $configTableId = sprintf('%s.%s', $this->getBucketId(), $config);

        if (!$this->storageApiClient->bucketExists($this->getBucketId())) {
            throw new UserException(sprintf('Configuration bucket %s does not exist', $this->getBucketId()));
        }
        if (!$this->storageApiClient->tableExists($configTableId)) {
            throw new UserException(sprintf('Configuration table %s does not exist', $configTableId));
        }

        $csv = $this->storageApiClient->exportTable($configTableId);
        $table = StorageApiClient::parseCsv($csv, true);

        $attributes = [];
        $tableInfo = $this->storageApiClient->getTable($configTableId);
        foreach ($tableInfo['attributes'] as $attr) {
            $attributes[$attr['name']] = $attr['value'];
        }

        foreach ($this->getRequiredBucketAttributes() as $attr) {
            if (!isset($attributes[$attr])) {
                throw new UserException(sprintf("Configuration table '%s' must have attribute '%s'",
                    $configTableId, $attr));
            }
        }

        if (!count($table)) {
            throw new UserException(sprintf('Configuration table \'%s\' is empty', $configTableId));
        }

        foreach ($this->getRequiredTableColumns() as $col) {
            if (!isset($table[0][$col])) {
                throw new UserException(sprintf('Configuration table \'%s\' must contain column \'%s\'',
                    $configTableId, $col));
            }
        }

        return [
            'attributes' => $attributes,
            'data' => $table
        ];
    }
}
