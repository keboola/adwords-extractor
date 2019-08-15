<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor\Test;

use Keboola\AdWordsExtractor\UserStorage;

class UserStorageTest extends \PHPUnit\Framework\TestCase
{
    public function testSaving(): void
    {
        $row1 = uniqid();
        $row2 = uniqid();
        $storage = new UserStorage(['table' => ['columns' => ['first', 'second']]], sys_get_temp_dir());
        $storage->save('table', ['first' => 'row1', 'second' => $row1]);
        $storage->save('table', ['first' => 'row2', 'second' => $row2]);

        $this->assertFileExists(sys_get_temp_dir().'/table.csv');
        /** @var resource $fp */
        $fp = fopen(sys_get_temp_dir().'/table.csv', 'r');
        if (!is_resource($fp)) {
            throw new \Exception('File does not exist: ' . sys_get_temp_dir().'/table.csv');
        }
        $row = 0;
        while (($data = fgetcsv($fp, 1000, ',')) !== false) {
            $row++;
            $this->assertCount(2, (array) $data);
            switch ($row) {
                case 1:
                    $this->assertEquals(['first', 'second'], $data);
                    break;
                case 2:
                    $this->assertEquals('row1', $data[0]);
                    $this->assertEquals($row1, $data[1]);
                    break;
                case 3:
                    $this->assertEquals('row2', $data[0]);
                    $this->assertEquals($row2, $data[1]);
                    break;
            }
        }
        $this->assertEquals(3, $row);
        fclose($fp);
    }

    public function testSavingToBucket(): void
    {
        $row1 = uniqid();
        $row2 = uniqid();
        $storage = new UserStorage(['table' => ['columns' => ['first', 'second']]], sys_get_temp_dir(), 'in.c-adwords');
        $storage->save('table', ['first' => 'row1', 'second' => $row1]);
        $storage->save('table', ['first' => 'row2', 'second' => $row2]);

        $this->assertFileExists(sys_get_temp_dir().'/in.c-adwords.table.csv');
        /** @var resource $fp */
        $fp = fopen(sys_get_temp_dir().'/in.c-adwords.table.csv', 'r');
        if (!is_resource($fp)) {
            throw new \Exception('File does not exist: ' . sys_get_temp_dir().'/in.c-adwords.table.csv');
        }
        $row = 0;
        while (($data = fgetcsv($fp, 1000, ',')) !== false) {
            $row++;
            $this->assertCount(2, (array) $data);
            switch ($row) {
                case 1:
                    $this->assertEquals(['first', 'second'], $data);
                    break;
                case 2:
                    $this->assertEquals('row1', $data[0]);
                    $this->assertEquals($row1, $data[1]);
                    break;
                case 3:
                    $this->assertEquals('row2', $data[0]);
                    $this->assertEquals($row2, $data[1]);
                    break;
            }
        }
        $this->assertEquals(3, $row);
        fclose($fp);
    }
}
