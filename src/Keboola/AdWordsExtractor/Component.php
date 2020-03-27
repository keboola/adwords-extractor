<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use GuzzleHttp\Exception\ClientException;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;

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

        $app = new Extractor($config, $this->getLogger(), $this->getDataDir() . '/out/tables');
        try {
            $app->extract($config->getQueries(), $config->getSince(), $config->getUntil());
        } catch (ClientException $e) {
            throw new UserException($e->getMessage(), $e->getCode(), $e);
        }
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
