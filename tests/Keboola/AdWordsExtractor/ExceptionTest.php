<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $e = Exception::apiError('message', 'method', ['a' => 1], ['b' => 2]);
        $message = json_decode($e->getMessage(), true);
        $this->assertNotFalse($message);
        $this->assertArrayHasKey('error', $message);
        $this->assertArrayHasKey('method', $message);
        $this->assertArrayHasKey('args', $message);
        $this->assertArrayHasKey('result', $message);
        $this->assertEquals('message', $message['error']);
        $this->assertEquals('method', $message['method']);
        $this->assertArrayHasKey('a', $message['args']);
        $this->assertEquals(1, $message['args']['a']);
        $this->assertArrayHasKey('b', $message['result']);
        $this->assertEquals(2, $message['result']['b']);
    }
}
