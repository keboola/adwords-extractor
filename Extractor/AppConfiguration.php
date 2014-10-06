<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 18.09.14
 * Time: 14:18
 */

namespace Keboola\AdWordsExtractorBundle\Extractor;

class AppConfiguration
{
	public $app_name;
	public $client_id;
	public $client_secret;

	public function __construct($appName, $mainConfig)
	{
		$this->app_name = $appName;

		$this->client_id = $mainConfig['client_id'];
		$this->client_secret = $mainConfig['client_secret'];
	}
}