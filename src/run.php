<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$logger = new \Keboola\Component\Logger();
try {
    $app = new \Keboola\AdWordsExtractor\Component($logger);
    $app->execute();
    exit(0);
} catch (\Throwable $e) {
    if ($e instanceof \Keboola\Component\UserException
        || $e instanceof \Keboola\AdWordsExtractor\Exception
        || $e instanceof \Google\AdsApi\AdWords\v201809\cm\ApiException) {
        $logger->error($e->getMessage());
        exit(1);
    }

    $previous = $e->getPrevious();
    $logger->critical(
        get_class($e) . ':' . $e->getMessage(),
        [
            'errFile' => $e->getFile(),
            'errLine' => $e->getLine(),
            'errCode' => $e->getCode(),
            'errTrace' => $e->getTraceAsString(),
            'errPrevious' => $previous ? get_class($previous) : '',
        ]
    );
    exit(2);
}
