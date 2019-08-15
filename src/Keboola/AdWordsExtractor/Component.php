<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{
    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();

        if (!file_exists($this->getDataDir() . '/out')) {
            mkdir($this->getDataDir() . '/out');
        }
        if (!file_exists($this->getDataDir() . '/out/tables')) {
            mkdir($this->getDataDir() . '/out/tables');
        }

        $app = new Extractor([
            'developerToken' => $config->getDeveloperToken(),
            'oauthAppKey' => $config->getOAuthApiAppKey(),
            'oauthAppSecret' => $config->getOAuthApiAppSecret(),
            'oauthRefreshToken' => $config->getRefreshToken(),
            'customerId' => $config->getCustomerId(),
            'bucket' => $config->getBucket(),
        ], $this->getLogger(), $this->getDataDir() . '/out/tables');
        $app->extract($config->getQueries(), $config->getSince(), $config->getUntil());
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
