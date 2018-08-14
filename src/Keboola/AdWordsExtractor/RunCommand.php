<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    const ERROR_DEPRECATED_COMPONENT = 'Google AdWords Reports (v201710) extractor is deprecated. Please migrate to newer version.';

    protected function configure()
    {
        $this->setName('run');
        $this->setDescription('Runs Extractor');
        $this->addArgument('data directory', InputArgument::REQUIRED, 'Data directory');
    }

    protected function execute(InputInterface $input, OutputInterface $consoleOutput)
    {
        $dataDirectory = $input->getArgument('data directory');

        $configFile = "$dataDirectory/config.json";
        if (!file_exists($configFile)) {
            throw new \Exception("Config file not found at path $configFile");
        }

        $consoleOutput->writeln(self::ERROR_DEPRECATED_COMPONENT);
        return 1;
    }

    public function validateInput($config)
    {
        if (!isset($config['image_parameters']['#developer_token'])) {
            throw new \Exception("Developer token is missing from image parameters");
        }
        if (!isset($config['authorization']['oauth_api']['credentials']['appKey'])
            || !isset($config['authorization']['oauth_api']['credentials']['#appSecret'])) {
            throw new Exception("Authorization credentials are missing. Have you authorized our app "
                . "for your AdWords account?");
        }
        $required = ['customerId', 'queries'];
        foreach ($required as $r) {
            if (empty($config['parameters'][$r])) {
                throw new Exception("Missing parameter '$r'");
            }
        }
        if (!is_array($config['parameters']['queries'])) {
            throw new Exception("Parameter 'queries' has to be array");
        }
        if (empty($config['parameters']['queries'])) {
            throw new Exception("Parameter 'queries' is empty");
        }
        foreach ($config['parameters']['queries'] as $q) {
            if (!isset($q['name']) || !isset($q['query'])) {
                throw new Exception("Items of array in parameter queries has to contain 'name', 'query' "
                    . "and optionally 'primary'");
            }
        }
        if (!isset($config['authorization']['oauth_api']['credentials']['#data'])) {
            throw new Exception("App configuration is missing oauth data, contact support please.");
        }
        $oauthData = json_decode($config['authorization']['oauth_api']['credentials']['#data'], true);
        if (!isset($oauthData['refresh_token'])) {
            throw new Exception("Missing refresh token, check your oAuth configuration");
        }
        return [
            'oauthKey' => $config['authorization']['oauth_api']['credentials']['appKey'],
            'oauthSecret' => $config['authorization']['oauth_api']['credentials']['#appSecret'],
            'refreshToken' => $oauthData['refresh_token'],
            'developerToken' => $config['image_parameters']['#developer_token'],
            'customerId' => $config['parameters']['customerId'],
            'since' => date('Ymd', strtotime(isset($config['parameters']['since'])
                ? $config['parameters']['since'] : '-1 day')),
            'until' => date('Ymd', strtotime(isset($config['parameters']['until'])
                ? $config['parameters']['until'] : '-1 day')),
            'queries' => $config['parameters']['queries']
        ];
    }
}
