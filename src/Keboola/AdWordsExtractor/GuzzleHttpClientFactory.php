<?php
/**
 * @package adwords-extractor
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\AdWordsExtractor;

use Google\AdsApi\Common\GuzzleLogMessageHandler;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpClientFactory implements \Google\AdsApi\Common\GuzzleHttpClientFactory
{
    const RETRIES_COUNT = 5;
    const MAINTENANCE_RETRIES_COUNT = 3;

    protected $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function generateHttpClient()
    {
        $handlerStack = HandlerStack::create();

        // Retry from maintenance
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $retries < self::MAINTENANCE_RETRIES_COUNT
                    && $response && ($response->getStatusCode() == 503 || $response->getStatusCode() == 423);
            },
            function ($retries) {
                return rand(60, 600) * 100;
            }
        ));

        // Retry for server errors
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= self::RETRIES_COUNT) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        $handlerStack->push(Middleware::cookies());
        $handlerStack->before('http_errors', GuzzleLogMessageHandler::log($this->logger));
        return new \GuzzleHttp\Client([
            'handler' => $handlerStack
        ]);
    }
}
