<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Keboola\Csv\CsvFile;

class UserStorage
{
    protected const DEFAULT_BUCKET = 'in.c-keboola-ex-adwords-v201809-%s';

    /** @var array  */
    protected $tables;
    /** @var string */
    protected $path;
    /** @var string|null */
    protected $bucket;
    /** @var array  */
    protected $files = [];

    public function __construct(array $tables, string $path, ?string $bucket = null)
    {
        $this->tables = $tables;
        $this->path = $path;
        $this->bucket = $bucket;
    }

    public static function getDefaultBucket(string $configId): string
    {
        return sprintf(UserStorage::DEFAULT_BUCKET, $configId);
    }

    public function save(string $table, array $data): void
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

    public function getReportFilename(string $table): string
    {
        return "$this->path/" . ($this->bucket ? "$this->bucket." : null) . "report-$table.csv";
    }

    public function createManifest(string $fileName, string $table, array $primary = []): void
    {
        if (!file_exists("$fileName.manifest")) {
            file_put_contents("$fileName.manifest", \GuzzleHttp\json_encode([
                'destination' => ($this->bucket ? "$this->bucket." : null) . $table,
                'incremental' => true,
                'primary_key' => $primary,
            ]));
        }
    }
}
