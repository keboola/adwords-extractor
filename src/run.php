<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

use Symfony\Component\Yaml\Yaml;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        if (0 === error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

require_once(dirname(__FILE__) . "/../vendor/autoload.php");
$arguments = getopt("d::", array("data::"));
if (!isset($arguments['data'])) {
    print "Data folder not set.";
    exit(1);
}
$config = Yaml::parse(file_get_contents($arguments['data'] . "/config.yml"));
if (!isset($config['parameters']['username'])) {
    print "Missing parameter username";
    exit(1);
}
if (!isset($config['parameters']['password']) && !isset($config['parameters']['#password'])) {
    print "Missing parameter password";
    exit(1);
}
if (!isset($config['parameters']['bucket'])) {
    print "Missing parameter bucket";
    exit(1);
}

if (!file_exists("{$arguments['data']}/out")) {
    mkdir("{$arguments['data']}/out");
}
if (!file_exists("{$arguments['data']}/out/tables")) {
    mkdir("{$arguments['data']}/out/tables");
}

try {
    $app = new \Keboola\AdWordsExtractor\Extractor(
        $config['parameters']['client_id'],
        $config['parameters']['client_secret'],
        $config['parameters']['developer_token'],
        $config['parameters']['refresh_token'],
        $config['parameters']['customer_id'],
        "{$arguments['data']}/out/tables",
        $config['parameters']['bucket']
    );

    $since = date('Ymd', strtotime(isset($config['parameters']['since']) ? $config['parameters']['since'] : '-1 day'));
    $until = date('Ymd', strtotime(isset($config['parameters']['until']) ? $config['parameters']['until'] : '-1 day'));
    $app->extract($config['parameters']['reports'], $since, $until);

    exit(0);
} catch (\Keboola\AdWordsExtractor\Exception $e) {
    print $e->getMessage();
    exit(1);
}
