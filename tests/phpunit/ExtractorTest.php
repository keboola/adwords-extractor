<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor\Test;

use Keboola\AdWordsExtractor\Extractor;
use Keboola\AdWordsExtractor\UserStorage;
use Keboola\Component\Logger;

class ExtractorTest extends AbstractTest
{

    public function testExtraction(): void
    {
        $this->prepareCampaign(uniqid());

        $date = date('Ymd', strtotime('-1 day'));
        $report = uniqid();
        $queries = [
            [
                'name' => $report,
                'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
            ],
        ];
        $e = new Extractor([
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'oauthAppKey' => EX_AW_CLIENT_ID,
            'oauthAppSecret' => EX_AW_CLIENT_SECRET,
            'oauthRefreshToken' => EX_AW_REFRESH_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
        ], new Logger(), sys_get_temp_dir());
        $e->extract($queries, $date, $date);

        $bucket = UserStorage::getDefaultBucket('default');
        $this->assertFileExists(sys_get_temp_dir()."/$bucket.customers.csv");
        /** @var array $fp */
        $fp = file(sys_get_temp_dir()."/$bucket.customers.csv");
        if (!count($fp)) {
            throw new \Exception('Report file not exists:' . sys_get_temp_dir()."/$bucket.customers.csv");
        }
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir()."/$bucket.campaigns.csv");
        /** @var array $fp */
        $fp = file(sys_get_temp_dir()."/$bucket.campaigns.csv");
        if (!count($fp)) {
            throw new \Exception('Report file not exists:' . sys_get_temp_dir()."/$bucket.camapigns.csv");
        }
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir()."/$bucket.report-$report.csv");
        /** @var array $fp */
        $fp = file(sys_get_temp_dir()."/$bucket.report-$report.csv");
        if (!count($fp)) {
            throw new \Exception('Report file not exists:' . sys_get_temp_dir()."/$bucket.report-$report.csv");
        }
        $this->assertGreaterThan(1, count($fp));
    }

    public function testWillFailIfTableUsesReservedName(): void
    {
        $date = date('Ymd', strtotime('-1 day'));
        $e = new Extractor([
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'oauthAppKey' => EX_AW_CLIENT_ID,
            'oauthAppSecret' => EX_AW_CLIENT_SECRET,
            'oauthRefreshToken' => EX_AW_REFRESH_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
        ], new Logger(), sys_get_temp_dir());

        $this->expectException(\Keboola\AdWordsExtractor\Exception::class);
        $this->expectExceptionMessage(
            '"campaigns" is reserved table name (customers, campaigns) that cannot be used for query result'
        );

        $e->extract([
            [
                'name' => 'campaigns',
                'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
            ],
        ], $date, $date);
    }

    public function testWillFailWithInvalidFieldInAwql(): void
    {
        $date = date('Ymd', strtotime('-1 day'));
        $e = new Extractor([
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'oauthAppKey' => EX_AW_CLIENT_ID,
            'oauthAppSecret' => EX_AW_CLIENT_SECRET,
            'oauthRefreshToken' => EX_AW_REFRESH_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
        ], new Logger(), sys_get_temp_dir());

        $this->expectException(\Keboola\AdWordsExtractor\Exception::class);
        $this->expectExceptionMessage(
            'Failed to get results for some queries, please check the log'
        );

        $e->extract([
            [
                'name' => 'my_table',
                'query' => 'SELECT Id, Status FROM CAMPAIGN_PERFORMANCE_REPORT',
            ],
        ], $date, $date);
    }
}
