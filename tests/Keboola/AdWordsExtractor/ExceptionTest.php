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
        $message = uniqid();
        $customerId = uniqid();
        $query = uniqid();
        $e = Exception::reportError($message, $customerId, $query, ['e' => 1]);
        $result = json_decode($e->getMessage(), true);
        $this->assertNotFalse($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('customerId', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertContains($message, $result['error']);
        $this->assertEquals($customerId, $result['customerId']);
        $this->assertEquals($query, $result['query']);
        $this->assertEquals(['e' => 1], $result['data']);
    }
}
