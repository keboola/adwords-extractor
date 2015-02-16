<?php
/**
 * @package adwords-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Service;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\Syrup\Exception\UserException;
use Keboola\Temp\Temp;

class UserStorage
{
    /**
     * @var \Keboola\StorageApi\Client
     */
    protected $storageApiClient;
    /**
     * @var \Keboola\Temp\Temp
     */
    protected $temp;
    protected $appName;
    protected $files = [];
    protected $tables = [];



    public function __construct($appName, Client $storageApi, Temp $temp)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApi;
        $this->temp = $temp;
    }

    public function getBucketId()
    {
        return 'in.c-'.$this->appName;
    }

    public function save($table, $data)
    {
        if (!isset($this->files[$table])) {
            $file = new CsvFile($this->temp->createTmpFile());
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;
        }

        if (!is_array($data)) {
            $data = (array)$data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = isset($data[$c]) ? $data[$c] : null;
        }

        /** @var CsvFile $file */
        $file = $this->files[$table];
        $file->writeRow($dataToSave);
    }

    public function uploadData()
    {
        if (!$this->storageApiClient->bucketExists($this->getBucketId())) {
            $this->storageApiClient->createBucket($this->appName, 'in', $this->appName.' Data Storage');
        }

        foreach($this->files as $name => $file) {
            $this->uploadTable(
                $name,
                $file,
                !empty($this->tables['primaryKey']) ? $this->tables['primaryKey'] : null
            );
        }
    }

    public function uploadTable($name, $file, $primaryKey = null)
    {
        $tableId = $this->getBucketId() . "." . $name;
        try {
            $options = array();
            if ($primaryKey) {
                $options['primaryKey'] = $primaryKey;
            }
            if($this->storageApiClient->tableExists($tableId)) {
                $this->storageApiClient->dropTable($tableId);
            }
            $this->storageApiClient->createTableAsync($this->getBucketId(), $name, $file, $options);
        } catch(\Keboola\StorageApi\ClientException $e) {
            throw new UserException(sprintf('Error during upload of table %s to Storage API. %s', $tableId, $e->getMessage()), $e);
        }
    }
}
