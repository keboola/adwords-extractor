<?php
/**
 * Created by Ondrej Vana <kachna@keboola.com>
 * Date: 17/09/14
 */

namespace Keboola\AdWordsExtractorBundle\Job;

use Keboola\AdWordsExtractorBundle\Extractor\AppConfiguration;
use Keboola\ExtractorBundle\Common\Configuration;
use Keboola\ExtractorBundle\Extractor\Extractor;
use Keboola\ExtractorBundle\Syrup\Job\Executor as ExExecutor;
use Monolog\Logger;
use Syrup\ComponentBundle\Filesystem\Temp;
use Syrup\ComponentBundle\Job\Metadata\Job;

class Executor extends ExExecutor
{
	protected $appName = "ex-adwords";
}
