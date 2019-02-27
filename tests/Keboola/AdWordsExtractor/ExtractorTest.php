<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor\Tests;

use Keboola\AdWordsExtractor\Extractor;
use Keboola\AdWordsExtractor\UserStorage;
use Symfony\Component\Console\Output\ConsoleOutput;

class ExtractorTest extends AbstractTest
{

    public function testExtraction()
    {
        $this->prepareCampaign(uniqid());

        $date = date('Ymd', strtotime('-1 day'));
        $report = uniqid();
        $e = new Extractor([
            'oauthKey' => EX_AW_CLIENT_ID,
            'oauthSecret' => EX_AW_CLIENT_SECRET,
            'refreshToken' => EX_AW_REFRESH_TOKEN,
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
            'outputPath' => sys_get_temp_dir(),
            'output' => new ConsoleOutput()
        ]);
        $e->extract([
            [
                'name' => $report,
                'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT'
            ]
        ], $date, $date);

        $bucket = UserStorage::getDefaultBucket('default');
        $this->assertFileExists(sys_get_temp_dir()."/$bucket.customers.csv");
        $fp = file(sys_get_temp_dir()."/$bucket.customers.csv");
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir()."/$bucket.campaigns.csv");
        $fp = file(sys_get_temp_dir()."/$bucket.campaigns.csv");
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir()."/$bucket.report-$report.csv");
        $fp = file(sys_get_temp_dir()."/$bucket.report-$report.csv");
        $this->assertGreaterThan(1, count($fp));
    }

    public function testWillFailIfTableUsesReservedName(): void
    {
        $date = date('Ymd', strtotime('-1 day'));
        $e = new Extractor([
            'oauthKey' => EX_AW_CLIENT_ID,
            'oauthSecret' => EX_AW_CLIENT_SECRET,
            'refreshToken' => EX_AW_REFRESH_TOKEN,
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
            'outputPath' => sys_get_temp_dir(),
            'output' => new ConsoleOutput()
        ]);

        $this->expectException(\Keboola\AdWordsExtractor\Exception::class);
        $this->expectExceptionMessage(
            '"campaigns" is reserved table name (customers, campaigns) that cannot be used for query result'
        );

        $e->extract([
            [
                'name' => 'campaigns',
                'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT'
            ]
        ], $date, $date);
    }

    public function testWillFailWithInvalidFieldInAwql(): void
    {
        $date = date('Ymd', strtotime('-1 day'));
        $e = new Extractor([
            'oauthKey' => EX_AW_CLIENT_ID,
            'oauthSecret' => EX_AW_CLIENT_SECRET,
            'refreshToken' => EX_AW_REFRESH_TOKEN,
            'developerToken' => EX_AW_DEVELOPER_TOKEN,
            'customerId' => EX_AW_CUSTOMER_ID,
            'outputPath' => sys_get_temp_dir(),
            'output' => new ConsoleOutput()
        ]);

        $this->expectException(\Keboola\AdWordsExtractor\Exception::class);
        $this->expectExceptionMessage(
            'Failed to get results for some queries, please check the log'
        );

        $e->extract([
            [
                'name' => 'my_table',
                'query' => 'SELECT Id, Status FROM CAMPAIGN_PERFORMANCE_REPORT'
            ]
        ], $date, $date);
    }
}
