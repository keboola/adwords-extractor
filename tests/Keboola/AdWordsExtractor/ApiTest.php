<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor\Tests;

use Keboola\AdWordsExtractor\Api;
use Keboola\Temp\Temp;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ApiTest extends AbstractTest
{
    /** @var  Api */
    protected $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new Api(EX_AW_CLIENT_ID, EX_AW_CLIENT_SECRET, EX_AW_DEVELOPER_TOKEN, EX_AW_REFRESH_TOKEN, new Logger('adwords-api', [new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING)]));
    }

    public function testApiGetCustomers()
    {
        $this->api->setCustomerId(EX_AW_CUSTOMER_ID);

        $accountFound = false;
        foreach ($this->api->getCustomersYielded() as $result) {
            foreach ($result['entries'] as $r) {
                if ($r->getCustomerId() == EX_AW_TEST_ACCOUNT_ID) {
                    $accountFound = true;
                }
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
        foreach ($this->api->getCampaignsYielded() as $result) {
            foreach ($result['entries'] as $r) {
                if ($r->getId() == $campaignId) {
                    $campaignFound = true;
                }
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
