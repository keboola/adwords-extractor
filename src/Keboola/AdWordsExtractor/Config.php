<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Keboola\Component\Config\BaseConfig;
use Keboola\Component\UserException;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Config extends BaseConfig
{
    public function __construct(array $config, ?ConfigurationInterface $configDefinition = null)
    {
        parent::__construct($config, $configDefinition);

        if (!count($this->getQueries())) {
            throw new UserException("Parameter 'queries' from configuration does not contain any query");
        }
    }

    public function getDeveloperToken(): string
    {
        $token = $this->getValue(['parameters', '#developerToken'], '');
        if ($token) {
            return $token;
        }

        if (!isset($this->getImageParameters()['#developer_token'])) {
            throw new \Exception('Developer token is missing from image parameters');
        }
        return $this->getImageParameters()['#developer_token'];
    }

    public function getBucket(): string
    {
        return $this->getValue(['parameters', 'bucket'], '');
    }

    protected function getDate(string $name): string
    {
        $date = $this->getValue(['parameters', $name], '-1 day');
        $time = strtotime($date);
        if ($time === false) {
            throw new UserException("Date $name in configuration is invalid.");
        }
        return date('Ymd', $time);
    }

    public function getSince(): string
    {
        return $this->getDate('since');
    }

    public function getUntil(): string
    {
        return $this->getDate('until');
    }

    public function getCustomerId(): string
    {
        return $this->getValue(['parameters', 'customerId']);
    }

    public function getQueries(): array
    {
        return $this->getValue(['parameters', 'queries'], []);
    }

    public function getRefreshToken(): string
    {
        if (!$this->getOAuthApiAppKey() || !$this->getOAuthApiAppSecret()) {
            throw new Exception('Authorization credentials are missing. Have you authorized our app '
                . 'for your AdWords account?');
        }
        if (!$this->getOAuthApiData()) {
            throw new Exception('App configuration is missing oauth data, contact support please.');
        }
        $oauthData = \GuzzleHttp\json_decode($this->getOAuthApiData(), true);
        if (!isset($oauthData['refresh_token'])) {
            throw new Exception('Missing refresh token, check your oAuth configuration');
        }
        return $oauthData['refresh_token'];
    }
}
