<?php

namespace Keboola\AdWordsExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor;
use Syrup\ComponentBundle\Exception\SyrupComponentException as Exception;
use GuzzleHttp\Client as Client;
use Keboola\AdWordsExtractorBundle\AdWordsExtractorJob;

class AdWordsExtractor extends Extractor
{
	protected $name = "adwords";

	public function run($config) {
/**
 *	REST Example:
 *		$client = new Client(
 * 			["base_url" => "https://api.example.com/v1/"]
 *		);
 */

/**
 *	WSDL Example:
 *		$options = array(
 * // 			"trace" => 1, // DEBUG
 *			"connection_timeout" => 15,
 *			"compression" => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
 *			"location" => $config["soapEndpoint"]
 *		);
 *		$client = new Client($config["soapEndpoint"] . "?WSDL", $options);
 *
 *		$this->parser = new WsdlParser($client->__getTypes());
 */

		foreach($config["data"] as $jobConfig) {
			// $this->parser is, by default, only pre-created when using JsonExtractor
			// Otherwise it must be created like Above example, OR withing the job itself
			$job = new AdWordsExtractorJob($jobConfig, $client, $this->parser);
			$job->run();
		}

		// ONLY available in the Json/Wsdl parsers -
		// otherwise just pass an array of CsvFile OR Common/Table files to upload
		$this->sapiUpload($this->parser->getCsvFiles());
	}
}
