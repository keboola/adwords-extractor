<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{

    protected function createCampaign($name)
    {
        $client = new \Zend\Http\Client(EX_SK_API_URL, [
            'adapter' => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120
            ],
            'timeout' => 120
        ]);
        $client = new \Zend\XmlRpc\Client(EX_SK_API_URL, $client);
        $result = $client->call('client.login', [EX_SK_USERNAME, EX_SK_PASSWORD]);
        $session = $result['session'];
        $result = $client->call('campaigns.create', [
            'user' => [
                'session' => $session
            ],
            'campaigns' => [[
                'name' => $name,
                'dayBudget' => 1000
            ]]
        ]);
        return $result['campaignIds'][0];
    }
}
