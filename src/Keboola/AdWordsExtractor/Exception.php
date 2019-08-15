<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

class Exception extends \Exception
{
    public static function reportError(string $message, string $customerId, string $query, array $data = []): Exception
    {
        $result = [
            'error' => 'DownloadReport Error. ' . $message,
            'customerId' => $customerId,
            'query' => $query,
        ];
        if ($data) {
            $result['data'] = $data;
        }
        return new static(\GuzzleHttp\json_encode($result));
    }
}
