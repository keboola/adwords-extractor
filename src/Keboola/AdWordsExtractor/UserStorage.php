<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

use Keboola\Csv\CsvFile;
use Symfony\Component\Yaml\Yaml;

class UserStorage
{
    protected $tables;
    protected $path;
    protected $bucket;

    protected $files = [];

    public function __construct(array $tables, $path, $bucket)
    {
        $this->tables = $tables;
        $this->path = $path;
        $this->bucket = $bucket;
    }

    public function save($table, $data)
    {
        if (!isset($this->files[$table])) {
            $file = new CsvFile("$this->path/$this->bucket.$table.csv");
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;

            file_put_contents("$this->path/$this->bucket.$table.csv.manifest", Yaml::dump([
                'destination' => "$this->bucket.$table",
                'incremental' => true,
                'primary_key' => isset($this->tables[$table]['primary']) ? $this->tables[$table]['primary'] : []
            ]));
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

    public function getReportFilename($table)
    {
        return "$this->path/$this->bucket.$table.csv";
    }

    public function saveReport($table, CsvFile $file, array $primary = [])
    {
        file_put_contents("$this->path/$this->bucket.$table.csv.manifest", Yaml::dump([
            'destination' => "$this->bucket.$table",
            'incremental' => true,
            'primary_key' => $primary
        ]));

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
}
