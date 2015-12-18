<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

class Exception extends \Exception
{
    public static function reportError($message, $customerId, $query, array $data = [])
    {
        $result = [
            'error' => 'DownloadReport Error. ' . $message,
            'customerId' => $customerId,
            'query' => $query
        ];
        if ($data) {
            $result['data'] = $data;
        }
        return new static(json_encode($result));
    }
}
