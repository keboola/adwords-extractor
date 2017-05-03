<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor\Tests;

use Keboola\AdWordsExtractor\UserStorage;

class UserStorageTest extends \PHPUnit\Framework\TestCase
{
    public function testSaving()
    {
        $row1 = uniqid();
        $row2 = uniqid();
        $storage = new UserStorage(['table' => ['columns' => ['first', 'second']]], sys_get_temp_dir());
        $storage->save('table', ['first' => 'row1', 'second' => $row1]);
        $storage->save('table', ['first' => 'row2', 'second' => $row2]);

        $this->assertFileExists(sys_get_temp_dir().'/table.csv');
        $fp = fopen(sys_get_temp_dir().'/table.csv', 'r');
        $row = 0;
        while (($data = fgetcsv($fp, 1000, ",")) !== false) {
            $row++;
            $this->assertCount(2, $data);
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
