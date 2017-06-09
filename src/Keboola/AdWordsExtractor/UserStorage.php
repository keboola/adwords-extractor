<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

use Keboola\Csv\CsvFile;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class UserStorage
{
    protected $tables;
    protected $path;
    protected $bucket;

    protected $files = [];

    public function __construct(array $tables, $path, $bucket = null)
    {
        $this->tables = $tables;
        $this->path = $path;
        $this->bucket = $bucket;
    }

    public function save($table, $data)
    {
        if (!isset($this->files[$table])) {
            $fileName = "$this->path/" . ($this->bucket ? "$this->bucket." : null) . "$table.csv";
            $file = new CsvFile($fileName);
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;

            $this->createManifest(
                $fileName,
                $table,
                isset($this->tables[$table]['primary']) ? $this->tables[$table]['primary'] : []
            );
        }

        if (!is_array($data)) {
            $data = (array)$data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = isset($data[$c]) ? $data[$c] : null;
        }

        if (count($dataToSave)) {
            /** @var CsvFile $file */
            $file = $this->files[$table];
            $file->writeRow($dataToSave);
        }
    }

    public function getReportFilename($table)
    {
        return "$this->path/" . ($this->bucket ? "$this->bucket." : null) . "report-$table.csv";
    }

    public function createManifest($fileName, $table, array $primary = [])
    {
        if (!file_exists("$fileName.manifest")) {
            $jsonEncode = new JsonEncode();
            file_put_contents("$fileName.manifest", $jsonEncode->encode([
                'destination' => ($this->bucket ? "$this->bucket." : null) . $table,
                'incremental' => true,
                'primary_key' => $primary
            ], JsonEncoder::FORMAT));
        }
    }
}
