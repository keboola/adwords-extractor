<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor\Tests;

use Keboola\AdWordsExtractor\Extractor;
use Keboola\AdWordsExtractor\UserStorage;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

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
            'logger' => new Logger('app-errors', [new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR)])
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
}
