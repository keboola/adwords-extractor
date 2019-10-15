<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor\Test\Functional;

use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecification;

class DatadirTest extends AbstractDatadirTestCase
{

    public function testRun(): void
    {
        $reportName = uniqid();
        $config = [
            'action' => 'run',
            'parameters' => [
                'customerId' => EX_AW_CUSTOMER_ID,
                'queries' => [
                    [
                        'name' => $reportName,
                        'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
                        'primary' => ['CampaignId'],
                    ],
                ],
            ],
            'authorization' => ['oauth_api' => ['credentials' => [
                'appKey' => EX_AW_CLIENT_ID,
                '#appSecret' => EX_AW_CLIENT_SECRET,
                '#data' => \GuzzleHttp\json_encode(['refresh_token' => EX_AW_REFRESH_TOKEN]),
            ]]],
            'image_parameters' => ['#developer_token' => EX_AW_DEVELOPER_TOKEN],
        ];

        $specification = new DatadirTestSpecification(
            __DIR__ . '/run/source/data',
            0,
            '',
            '',
        );
        $tempDatadir = $this->getTempDatadir($specification);
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', \GuzzleHttp\json_encode($config));
        $process = $this->runScript($tempDatadir->getTmpFolder());
        $this->assertEmpty($process->getErrorOutput());
        $this->assertEquals(0, $process->getExitCode());

        $filePrefix = $tempDatadir->getTmpFolder() . '/out/tables/in.c-keboola-ex-adwords-v201809-default';
        $csv = file("$filePrefix.campaigns.csv");
        $this->assertNotFalse($csv);
        if ($csv !== false) {
            $this->assertEquals(
                '"customerId","id","name","status","servingStatus","startDate","endDate",'
                . '"adServingOptimizationStatus","advertisingChannelType"',
                trim($csv[0])
            );
        }
        $csv = file("$filePrefix.customers.csv");
        $this->assertNotFalse($csv);
        if ($csv !== false) {
            $this->assertEquals(
                '"customerId","name","companyName","canManageClients","currencyCode","dateTimeZone"',
                trim($csv[0])
            );
        }
        $csv = file("$filePrefix.report-$reportName.csv");
        $this->assertNotFalse($csv);
        if ($csv !== false) {
            $this->assertEquals('Campaign ID,Impressions,Clicks', trim($csv[0]));
        }
    }

    public function testRunWithUserDefinedDeveloperToken(): void
    {
        $reportName = uniqid();
        $config = [
            'action' => 'run',
            'parameters' => [
                'customerId' => EX_AW_CUSTOMER_ID,
                '#developerToken' => EX_AW_DEVELOPER_TOKEN,
                'queries' => [
                    [
                        'name' => $reportName,
                        'query' => 'SELECT CampaignId, Impressions, Clicks FROM CAMPAIGN_PERFORMANCE_REPORT',
                        'primary' => ['CampaignId'],
                    ],
                ],
            ],
            'authorization' => ['oauth_api' => ['credentials' => [
                'appKey' => EX_AW_CLIENT_ID,
                '#appSecret' => EX_AW_CLIENT_SECRET,
                '#data' => \GuzzleHttp\json_encode(['refresh_token' => EX_AW_REFRESH_TOKEN]),
            ]]],
            'image_parameters' => ['#developer_token' => 'invalid'],
        ];

        $specification = new DatadirTestSpecification(
            __DIR__ . '/run/source/data',
            0,
            '',
            '',
        );
        $tempDatadir = $this->getTempDatadir($specification);
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', \GuzzleHttp\json_encode($config));
        $process = $this->runScript($tempDatadir->getTmpFolder());
        $this->assertEmpty($process->getErrorOutput());
        $this->assertEquals(0, $process->getExitCode());
    }
}
