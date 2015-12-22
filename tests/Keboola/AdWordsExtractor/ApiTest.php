<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Keboola\Temp\Temp;

class ApiTest extends AbstractTest
{
    /** @var  Api */
    protected $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new Api(EX_AW_CLIENT_ID, EX_AW_CLIENT_SECRET, EX_AW_DEVELOPER_TOKEN, EX_AW_REFRESH_TOKEN);
        $this->api->setUserAgent(EX_AW_USER_AGENT);
    }

    public function testApiGetCustomers()
    {
        $this->api->setCustomerId(EX_AW_CUSTOMER_ID);

        $accountFound = false;
        foreach ($this->api->getCustomers() as $r) {
            if ($r->customerId == EX_AW_TEST_ACCOUNT_ID) {
                $accountFound = true;
            }
        }
        $this->assertTrue($accountFound);
    }

    public function testApiGetCampaigns()
    {
        $this->api->setCustomerId(EX_AW_TEST_ACCOUNT_ID);

        $campaignName = uniqid();
        $campaignId = $this->prepareCampaign($campaignName);

        $campaignFound = false;
        foreach ($this->api->getCampaigns() as $r) {
            if ($r->id == $campaignId) {
                $campaignFound = true;
            }
        }
        $this->assertTrue($campaignFound);
    }

    public function testApiGetReport()
    {
        $this->api->setCustomerId(EX_AW_TEST_ACCOUNT_ID);

        $temp = new Temp();
        $this->api->setTemp($temp);
        $file = $temp->getTmpFolder().'/'.uniqid();

        $campaignName = uniqid();
        $campaignId = $this->prepareCampaign($campaignName);

        $date = date('Ymd', strtotime('-1 day'));
        $this->api->getReport(
            'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
            $date,
            $date,
            $file
        );

        $campaignFound = false;
        $isHeader = true;
        $fp = fopen($file, 'r');
        while (($data = fgetcsv($fp, 1000, ",")) !== false) {
            if ($isHeader) {
                $this->assertEquals(['Campaign ID', 'Impressions', 'Clicks'], $data);
                $isHeader = false;
            }
            $this->assertCount(3, $data);
            if ($data[0] == $campaignId) {
                $campaignFound = true;
            }
        }
        $this->assertTrue($campaignFound);
    }
}
