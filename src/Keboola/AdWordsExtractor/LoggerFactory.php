<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 14/09/16
 * Time: 13:57
 */

namespace Keboola\AdWordsExtractor;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerFactory
{

    public static function createLogger()
    {
        $handler = new StreamHandler('php://stdout');
        $handler->setFormatter(new LineFormatter("%message%\n"));

        return new Logger('keboola.ex-adwords', [$handler]);
    }
}
