<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{

    private function initUser()
    {
        $user = new \AdWordsUser();
        $user->SetDeveloperToken(EX_AW_DEVELOPER_TOKEN);
        $user->SetOAuth2Info([
            'client_id' => EX_AW_CLIENT_ID,
            'client_secret' => EX_AW_CLIENT_SECRET,
            'refresh_token' => EX_AW_REFRESH_TOKEN
        ]);
        $handler = $user->GetOAuth2Handler();
        $credentials = $handler->RefreshAccessToken($user->GetOAuth2Info());
        $user->SetOAuth2Info($credentials);
        $user->setUserAgent(EX_AW_USER_AGENT);
        $user->SetClientCustomerId(EX_AW_TEST_ACCOUNT_ID);
        return $user;
    }

    protected function prepareCampaign($name)
    {
        $user = $this->initUser();
        $service = $user->GetService('CampaignService');

        // Delete all campaigns
        $selector = new \Selector();
        $selector->fields = ['Id'];
        $selector->paging = new \Paging(0, \AdWordsConstants::RECOMMENDED_PAGE_SIZE);
        do {
            $page = $service->get($selector);
            if (isset($page->entries)) {
                $operations = [];
                foreach ($page->entries as $campaign) {
                    $campaign->status = 'REMOVED';

                    $operation = new \CampaignOperation();
                    $operation->operator = 'SET';
                    $operation->operand = $campaign;
                    $operations[] = $operation;
                }
                $service->mutate($operations);
            }

            $selector->paging->startIndex += \AdWordsConstants::RECOMMENDED_PAGE_SIZE;
        } while ($page->totalNumEntries > $selector->paging->startIndex);

        // Create new campaign
        $budgetService = $user->GetService('BudgetService');
        $budget = new \Budget();
        $budget->name = uniqid();
        $budget->period = 'DAILY';
        $budget->amount = new \Money(50000000);
        $budget->deliveryMethod = 'STANDARD';
        $operations = [];

        $operation = new \BudgetOperation();
        $operation->operand = $budget;
        $operation->operator = 'ADD';
        $operations[] = $operation;

        $result = $budgetService->mutate($operations);
        $budget = $result->value[0];


        $campaign = new \Campaign();
        $campaign->name = $name;
        $campaign->advertisingChannelType = 'SEARCH';
        $campaign->budget = new \Budget();
        $campaign->budget->budgetId = $budget->budgetId;
        $biddingStrategyConfiguration = new \BiddingStrategyConfiguration();
        $biddingStrategyConfiguration->biddingStrategyType = 'MANUAL_CPC';
        $campaign->biddingStrategyConfiguration = $biddingStrategyConfiguration;

        $operation = new \CampaignOperation();
        $operation->operator = 'ADD';
        $operation->operand = $campaign;
        $operations = array($operation);

        $result = $service->mutate($operations);
        $campaign = $result->value[0];
        return (string)$campaign->id;
    }
}
