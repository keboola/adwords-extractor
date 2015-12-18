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
        $this->createCampaign(uniqid());

        $startDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 00:00:01', strtotime('-2 days')));
        $endDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 00:00:01', strtotime('-1 day')));

        $e = new Extractor(EX_SK_USERNAME, EX_SK_PASSWORD, sys_get_temp_dir(), 'out.c-main', EX_SK_API_URL);
        $e->run($startDate, $endDate);

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.accounts.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.accounts.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.stats.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.stats.csv');
        $this->assertGreaterThan(1, count($fp));
    }
}
