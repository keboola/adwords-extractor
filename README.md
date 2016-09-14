# adwords-extractor
KBC Docker app for extracting data from Google AdWords

The Extractor gets list of accessible clients, list of their campaigns and defined AWQL queries for given date range and saves the data to Storage API.

## Status

[![Build Status](https://travis-ci.org/keboola/adwords-extractor.svg)](https://travis-ci.org/keboola/adwords-extractor) [![Code Climate](https://codeclimate.com/github/keboola/adwords-extractor/badges/gpa.svg)](https://codeclimate.com/github/keboola/adwords-extractor)

Uses [googleads-php-lib](https://github.com/googleads/googleads-php-lib) version **13.0** with API **v201607**.

## Access Tokens
You have to apply for AdWords Developer Token in your MCC, see [https://developers.google.com/adwords/api/docs/signingup#step2a]

Once you get the Developer Token you can request Refresh Token using extractor's UI in Keboola or directly using oAuth API
(see http://docs.oauthv2.apiary.io/#).

Please note that refresh token is bound to used Google account and will stop working if someone changes it's password.

## Configuration

- **parameters**:
    - **developer_token** - Your developer token
    - **customer_id** - Instructions to get it are here: https://support.google.com/adwords/answer/1704344?hl=en
    - **bucket** - Name of bucket where the data will be saved
    - **since** *(optional)* - start date of downloaded stats (default is "-1 day")
    - **until** *(optional)* - end date of downloaded stats (default is "-1 day")
    - **queries** - Array of reports to download as Ad-hoc report, each item must contain:
        - **name** - Name of query, data will be saved to table `[bucket].[name]`.
        *Note that `customers` and `campaigns` are reserved names, thus cannot be used as query names.*
        - **query** - AWQL query for downloading Ad-hoc report (see [https://developers.google.com/adwords/api/docs/guides/awql]). You should pick columns to download from allowed report values and FROM clause from allowed report types
        - **primary** - Array of columns to be used as primary key. _You must use **Display Name** of the columns as defined in reports types documentation [https://developers.google.com/adwords/api/docs/appendix/reports]_ and replace spaces with underscores (e.g. for *CampaignId* use *Campaign_ID* and for *Date* use *Day*)
- **authorization**:
    - **oauth_api**:
        - **id** - identifier of oAuth API credentials (see http://docs.oauthv2.apiary.io/#reference/credentials/retrieve-credentials/get-credentials)

Example:
```
{
    "parameters": {
        "developer_token": "...",
        "refresh_token": "...",
        "customer_id": "91165040",
        "bucket": "in.c-ex-adwords",
        "queries": [
            {
                "name": "campaign-performance",
                "query": "SELECT CampaignId,Date,AverageCpc,AverageCpm,AveragePosition,Clicks,Cost,Impressions,AdNetworkType1 FROM CAMPAIGN_PERFORMANCE_REPORT",
                "primary": ["Campaign_ID", "Day"]
            }
        ]
    },
    "authorization": {
        "oauth_api": {
            "id": "refresh_token"
        }
    }
}
```


> **NOTICE!**
>
> - Date range in AWQL queries is assembled by the extractor according to API call parameters, so setting it manually
> won't work
> - Money values are in micros so you have to divide by million to get values in whole units, currency depends on account settings
> - Query names `customers` and `campaigns` are reserved, you cannot use them in your configuration.

## Output

Data are saved to these tables **incrementally**:

**customers** - contains data of all customers accessible from the main account, columns are:

- **name**: The name used by the manager to refer to the client
- **login**: The email address of the account's first login user, if any
- **companyName**: The company name of the account, if any
- **customerId**: The 10-digit ID that uniquely identifies the AdWords account
- **canManageClients**: Whether this account can manage clients
- **currencyCode**: The currency in which this account operates, see [supported currencies](https://developers.google.com/adwords/api/docs/appendix/currencycodes)
- **dateTimeZone**: The local timezone ID for this customer, see [supported zones](https://developers.google.com/adwords/api/docs/appendix/timezones)

**campaigns** - contains list of campaigns of all customers accessible from the main account, columns are:

- **customerId**: customer id (foreign key to table **customers**)
- **id**: ID of the campaign
- **name**: Name of the campaign
- **campaignStatus**: Status of this campaign, can be: **ENABLED, PAUSED, REMOVED**
- **servingStatus**: Serving status, can be: **SERVING, NONE, ENDED, PENDING, SUSPENDED**
- **startDate**: Date the campaign begins
- **endDate**: Date the campaign ends
- **adServingOptimizationStatus**: Ad serving optimization status, can be: **OPTIMIZE, CONVERSION_OPTIMIZE, ROTATE, ROTATE_INDEFINITELY, UNAVAILABLE**
- **advertisingChannelType**: The primary serving target for ads within this campaign, can be: **UNKNOWN, SEARCH, DISPLAY, SHOPPING**
- **displaySelect**: Indicates if a campaign is a search network with display select enabled campaign
- **trackingUrlTemplate**: URL template for constructing a tracking URL

Other tables will be created according to your reports configuration



## Installation

If you want to run this app standalone:

1. Clone the repository: `git@github.com:keboola/adwords-extractor.git ex-adwords`
2. Go to the directory: `cd ex-adwords`
3. Install composer: `curl -s http://getcomposer.org/installer | php`
4. Install packages: `php composer.phar install`
5. Create folder `data`
6. Create file `data/config.yml` with configuration, e.g.:

    ```
    parameters:
      developer_token:
      customer_id:
      bucket: in.c-ex-adwords
      reports:
        ...
    authorization:
        oauth_api:
            credentials:
                #data: "refresh_token:\"{your_token}\""
    ```
7. Run: `php src/run.php --data=./data`
8. Data tables will be saved to directory `data/out/tables`


## Testing

1. Create new oAuth credentials in Google Developers Console and get **client id** and **client secret**
2. Apply for AdWords Developer token
3. Create test manager account (see [https://developers.google.com/adwords/api/docs/test-accounts?hl=en]) and generate refresh token using this account
4. Create a client account under the test manager account from AdWords frontend

Run `docker-compose run --rm tests` with these env variables set from previous steps:

- **EX_AW_CLIENT_ID** - oAuth client id
- **EX_AW_CLIENT_SECRET** - oAuth client secret
- **EX_AW_DEVELOPER_TOKEN** - developer token
- **EX_AW_REFRESH_TOKEN** - generated refresh token
- **EX_AW_CUSTOMER_ID** - customer id of testing manager account
- **EX_AW_TEST_ACCOUNT** - customer id of testing client account
