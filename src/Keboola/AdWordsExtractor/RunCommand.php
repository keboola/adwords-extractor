<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Symfony\Component\Console\Output\ConsoleOutput;
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

        $configFile = "$dataDirectory/config.json";
        if (!file_exists($configFile)) {
            throw new \Exception("Config file not found at path $configFile");
        }
        $jsonDecode = new JsonDecode(true);
        $config = $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);

        try {
            $outputPath = "$dataDirectory/out/tables";
            (new Filesystem())->mkdir([$outputPath]);

            $validatedConfig = $this->validateInput($config);
            $validatedConfig['outputPath'] = $outputPath;
            $validatedConfig['output'] = $consoleOutput;
            if (!empty($config['parameters']['bucket'])) {
                $validatedConfig['bucket'] = $config['parameters']['bucket'];
            }

            $app = new Extractor($validatedConfig);
            $app->extract($validatedConfig['queries'], $validatedConfig['since'], $validatedConfig['until']);

            return 0;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $output = $e->getMessage();
            if (strpos($e->getMessage(), 'invalid_grant') !== false) {
                $output .= ' Try to re-authorize your component.';
            }
            $consoleOutput->writeln($output);
            return 1;
        } catch (ApiException $e) {
            $consoleOutput->writeln($e->getMessage());
            return 1;
        } catch (Exception $e) {
            $consoleOutput->writeln($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            if ($consoleOutput instanceof ConsoleOutput) {
                $consoleOutput->getErrorOutput()->writeln("{$e->getMessage()}\n{$e->getTraceAsString()}");
            } else {
                $consoleOutput->writeln("{$e->getMessage()}\n{$e->getTraceAsString()}");
            }
            return 2;
        }
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
