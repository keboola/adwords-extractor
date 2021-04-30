<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor\Test;

use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Keboola\AdWordsExtractor\Api;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;

class ApiTest extends AbstractTest
{
    /** @var  Api */
    protected $api;

    /** @var LoggerInterface */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = new TestLogger();

        $this->api = new Api(getenv('EX_AW_DEVELOPER_TOKEN'), $this->logger);
        $this->api->setOAuthCredentials(
            getenv('EX_AW_CLIENT_ID'),
            getenv('EX_AW_CLIENT_SECRET'),
            getenv('EX_AW_REFRESH_TOKEN')
        );
    }

    public function testApiGetCustomers(): void
    {
        $this->api->setCustomerId(EX_AW_CUSTOMER_ID);
        $accountFound = false;
        foreach ($this->api->getCustomersYielded(null, null, 1) as $result) {
            foreach ($result['entries'] as $r) {
                if ((string) $r->getCustomerId() === (string) EX_AW_TEST_ACCOUNT_ID) {
                    $accountFound = true;
                }
            }
        }
        $this->assertTrue($accountFound);
    }

    public function testApiGetCampaigns(): void
    {
        $this->api->setCustomerId(EX_AW_TEST_ACCOUNT_ID);

        $campaignName = uniqid();
        $campaignId = $this->prepareCampaign($campaignName);

        $campaignFound = false;
        foreach ($this->api->getCampaignsYielded() as $result) {
            foreach ($result['entries'] as $r) {
                if ((string) $r->getId() === (string) $campaignId) {
                    $campaignFound = true;
                }
            }
        }
        $this->assertTrue($campaignFound);
    }

    public function testApiGetReport(): void
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
        if ($fp === false) {
            throw new \Exception("Report file ($file) empty.");
        }
        while (($data = fgetcsv($fp, 1000, ',')) !== false) {
            if ($isHeader) {
                $this->assertEquals(['Campaign ID', 'Impressions', 'Clicks'], $data);
                $isHeader = false;
            }
            $this->assertCount(3, (array) $data);
            if ($data[0] === $campaignId) {
                $campaignFound = true;
            }
        }
        $this->assertTrue($campaignFound);
    }


    public function testRetry(): void
    {
        $this->api->setCustomerId(getenv('EX_AW_TEST_ACCOUNT_ID'));

        $temp = new Temp();
        $this->api->setTemp($temp);
        $file = $temp->getTmpFolder() . '/' . uniqid();

        $date = date('Ymd', strtotime('-1 day'));
        try {
            $this->api->getReport(
                'SELECT CampaignIda, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
                $date,
                $date,
                $file
            );
        } catch (ApiException $e) {
            $this->assertStringContainsString('INVALID_FIELD_NAME_FOR_REPORT', $e->getMessage());
        }
        $this->assertTrue($this->logger->hasInfoThatContains('Retrying... [1x]'));
        $this->assertTrue($this->logger->hasInfoThatContains('Retrying... [2x]'));
        $this->assertTrue($this->logger->hasInfoThatContains('Retrying... [3x]'));
        $this->assertTrue($this->logger->hasInfoThatContains('Retrying... [4x]'));
    }
}
