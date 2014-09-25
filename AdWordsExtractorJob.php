<?php

namespace Keboola\AdWordsExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonJob as ExtractorJob,
	Keboola\ExtractorBundle\Common\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException as Exception;

class AdWordsExtractorJob extends ExtractorJob
{
	protected $configName;

	/**
	 * @brief Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage() {
		/*
		$params = Utils::json_decode($this->config["parameters"], true);
		$url = Utils::buildUrl(trim($this->config["endpoint"], "/"), $params);

		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		return $this->client->createRequest("GET", $url);
		*/
	}

	/**
	 * @brief Return a download request OR false if no next page exists
	 *
	 * @param $response
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request | false
	 */
	protected function nextPage($response) {
		/*
		if (empty($response->pagination->next_url)) {
			return false;
		}

		return $this->client->createRequest("GET", $response->pagination->next_url);
		*/
	}

	/**
	 * @brief Call the parser and handle its return value
	 * - Wsdl and Json parsers results should be accessed by Parser::getCsvFiles()
	 * - JsonMap parser data should be saved to a CsvFile, OR a CsvFile must be provided as a second parameter to parser
	 * - JsonMap accepts a single row to parse()
	 * - Json::process(), Json::parse() (OBSOLETE) and Wsdl::parse() accept complete datasets (a full page)
	 *
	 * @param object $response
	 */
	protected function parse($response) {
		/**
		 * Edit according to the parser used
		 */
		$this->parser->process($response->data, $this->configName);
	}
}
