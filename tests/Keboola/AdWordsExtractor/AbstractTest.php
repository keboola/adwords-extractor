<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor\Tests;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201809\cm\BiddingStrategyConfiguration;
use Google\AdsApi\AdWords\v201809\cm\Budget;
use Google\AdsApi\AdWords\v201809\cm\BudgetOperation;
use Google\AdsApi\AdWords\v201809\cm\BudgetService;
use Google\AdsApi\AdWords\v201809\cm\Campaign;
use Google\AdsApi\AdWords\v201809\cm\CampaignOperation;
use Google\AdsApi\AdWords\v201809\cm\CampaignPage;
use Google\AdsApi\AdWords\v201809\cm\CampaignService;
use Google\AdsApi\AdWords\v201809\cm\Money;
use Google\AdsApi\AdWords\v201809\cm\Operator;
use Google\AdsApi\AdWords\v201809\cm\Paging;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\Auth\Credentials\UserRefreshCredentials;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

abstract class AbstractTest extends \PHPUnit\Framework\TestCase
{

    private function initSession()
    {
        $credential = new UserRefreshCredentials('https://www.googleapis.com/auth/adwords', [
            'client_id' => EX_AW_CLIENT_ID,
            'client_secret' => EX_AW_CLIENT_SECRET,
            'refresh_token' => EX_AW_REFRESH_TOKEN
        ]);
        $logger = new Logger('adwords-api', [new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING)]);
        return (new AdWordsSessionBuilder())
            ->withDeveloperToken(EX_AW_DEVELOPER_TOKEN)
            ->withOAuth2Credential($credential)
            ->withClientCustomerId(EX_AW_TEST_ACCOUNT_ID)
            ->withSoapLogger($logger)
            ->withReportDownloaderLogger($logger)
            ->build();
    }

    protected function prepareCampaign($name)
    {
        $session = $this->initSession();
        $services = new AdWordsServices();
        $service = $services->get($session, CampaignService::class);

        // Delete all campaigns
        $selector = new Selector();
        $selector->setFields(['Id']);
        $selector->setPaging(new Paging(0, 100));
        $operations = [];
        $totalNumEntries = 0;
        do {
            /** @var CampaignPage $page */
            $page = $service->get($selector);
            if ($page->getEntries() !== null) {
                $totalNumEntries = $page->getTotalNumEntries();
                foreach ($page->getEntries() as $campaign) {
                    $campaign->setStatus('REMOVED');

                    $operation = new CampaignOperation();
                    $operation->setOperand($campaign);
                    $operation->setOperator(Operator::SET);
                    $operations[] = $operation;
                }
            }
            $selector->getPaging()->setStartIndex($selector->getPaging()->getStartIndex() + 100);
        } while ($selector->getPaging()->getStartIndex() < $totalNumEntries);
        if (count($operations)) {
            $service->mutate($operations);
        }

        // Create new campaign
        $budgetService = $services->get($session, BudgetService::class);
        $budget = new Budget();
        $budget->setName(uniqid());
        $money = new Money();
        $money->setMicroAmount(50000000);
        $budget->setAmount($money);
        $budget->setDeliveryMethod('STANDARD');
        $operations = [];

        $operation = new BudgetOperation();
        $operation->setOperand($budget);
        $operation->setOperator('ADD');
        $operations[] = $operation;

        $result = $budgetService->mutate($operations);
        $budget = $result->getValue()[0];


        $campaign = new Campaign();
        $campaign->setName($name);
        $campaign->setAdvertisingChannelType('SEARCH');
        $campaignBudget = new Budget();
        $campaignBudget->setBudgetId($budget->getBudgetId());
        $campaign->setBudget($campaignBudget);
        $biddingStrategyConfiguration = new BiddingStrategyConfiguration();
        $biddingStrategyConfiguration->setBiddingStrategyType('MANUAL_CPC');
        $campaign->setBiddingStrategyConfiguration($biddingStrategyConfiguration);

        $operation = new CampaignOperation();
        $operation->setOperator('ADD');
        $operation->setOperand($campaign);
        $operations = array($operation);

        $result = $service->mutate($operations);
        $campaign = $result->getValue()[0];
        return (string)$campaign->getId();
    }
}
