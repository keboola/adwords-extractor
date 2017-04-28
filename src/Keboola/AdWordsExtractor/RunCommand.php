<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    protected function configure()
    {
        $this->setName('run');
        $this->setDescription('Runs Extractor');
        $this->addArgument('data directory', InputArgument::REQUIRED, 'Data directory');
    }

    protected function execute(InputInterface $input, OutputInterface $consoleOutput)
    {
        $dataDirectory = $input->getArgument('data directory');
        $logger = new Logger('app-errors', [new ErrorLogHandler]);

        $configFile = "$dataDirectory/config.json";
        if (!file_exists($configFile)) {
            throw new \Exception("Config file not found at path $configFile");
        }
        $jsonDecode = new JsonDecode(true);
        $config = $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);

        try {
            $oauthParameters = new Parameters(new OAuthParametersDefinition(), $config);
        } catch (InvalidConfigurationException $e) {
            $consoleOutput->writeln('Authorization credentials are missing. Have you authorized our app for your AdWords account?');
            return 1;
        }
        $oauthData = $jsonDecode->decode($oauthParameters['authorization']['oauth_api']['credentials']['#data'], JsonEncoder::FORMAT);
        if (!isset($oauthData['refresh_token'])) {
            $consoleOutput->writeln('Missing refresh token, check your oAuth configuration');
            return 1;
        }

        try {
            $configParameters = new Parameters(new ConfigParametersDefinition(), $config);
        } catch (InvalidConfigurationException $e) {
            $consoleOutput->writeln($e->getMessage());
            return 1;
        }

        $outputPath = "$dataDirectory/out/tables";
        (new Filesystem())->mkdir([$outputPath]);

        try {
            $app = new Extractor([
                'oauthKey' => $oauthParameters['authorization']['oauth_api']['credentials']['appKey'],
                'oauthSecret' => $oauthParameters['authorization']['oauth_api']['credentials']['#appSecret'],
                'refreshToken' => $oauthData['refresh_token'],
                'developerToken' => $configParameters['parameters']['#developer_token'],
                'customerId' => $configParameters['parameters']['customer_id'],
                'outputPath' => $outputPath
            ]);

            $since = date('Ymd', strtotime(isset($config['parameters']['since']) ? $config['parameters']['since'] : '-1 day'));
            $until = date('Ymd', strtotime(isset($config['parameters']['until']) ? $config['parameters']['until'] : '-1 day'));
            $app->extract($configParameters['parameters']['queries'], $since, $until);

            return 0;
        } catch (Exception $e) {
            $consoleOutput->writeln($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $logger->error($e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 2;
        }
    }
}
