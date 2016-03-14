<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

class ExtractorTest extends AbstractTest
{

    public function testExtraction()
    {
        $this->prepareCampaign(uniqid());

        $date = date('Ymd', strtotime('-1 day'));
        $report = uniqid();
        $e = new Extractor(
            EX_AW_CLIENT_ID,
            EX_AW_CLIENT_SECRET,
            EX_AW_DEVELOPER_TOKEN,
            EX_AW_REFRESH_TOKEN,
            EX_AW_CUSTOMER_ID,
            sys_get_temp_dir(),
            'out.c-main'
        );
        $e->extract([
            [
                'name' => $report,
                'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT'
            ]
        ], $date, $date);

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.customers.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.customers.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.report-'.$report.'.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.report-'.$report.'.csv');
        $this->assertGreaterThan(1, count($fp));
    }
}
