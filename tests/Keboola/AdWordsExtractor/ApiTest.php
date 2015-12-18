<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

class ApiTest extends AbstractTest
{
    /** @var  Api */
    protected $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new Api(EX_SK_USERNAME, EX_SK_PASSWORD, EX_SK_API_URL);
    }

    public function testApiLogin()
    {
        $result = $this->api->login();
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']);
    }

    public function testApiLogout()
    {
        $result = $this->api->logout();
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
    }

    public function testApiGetListLimit()
    {
        $result = $this->api->getListLimit();
        $this->assertGreaterThan(0, $result);
    }

    public function testApiGetAccounts()
    {
        $result = $this->api->getAccounts();
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('userId', $result[0]);
        $this->assertArrayHasKey('username', $result[0]);
        $this->assertEquals(EX_SK_USERNAME, $result[0]['username']);
    }

    public function testApiGetCampaigns()
    {
        $campaignName = uniqid();
        $campaignId = $this->createCampaign($campaignName);

        $result = $this->api->getCampaigns((int)EX_SK_USER_ID);
        $this->assertGreaterThan(1, count($result));
        $campaignFound = false;
        foreach ($result as $r) {
            $this->assertArrayHasKey('id', $r);
            $this->assertArrayHasKey('name', $r);
            if ($r['id'] == $campaignId) {
                $this->assertEquals($campaignName, $r['name']);
                $campaignFound = true;
            }
        }
        $this->assertTrue($campaignFound);
    }

    public function testApiGetStats()
    {
        $campaignName = uniqid();
        $campaignId = $this->createCampaign($campaignName);

        $date = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 00:00:01'));
        $result = $this->api->getStats(EX_SK_USER_ID, [$campaignId], $date, $date);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('stats', $result[0]);
        $this->assertCount(1, $result[0]['stats']);
        $this->assertArrayHasKey('conversions', $result[0]['stats'][0]);
        $this->assertArrayHasKey('clicks', $result[0]['stats'][0]);
    }
}
