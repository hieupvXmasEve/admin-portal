<?php

declare(strict_types=1);

require_once __DIR__.'/GetCheckSum.php';
require_once __DIR__.'/push-debt-to-dng.php';

$requestData = [
    'access_code' => 'lDf0pQO8ODS4V61JAkV833dRSix1IU4BBcdtvKst82wF4TuwyfwYRXpsAyqhtYy4KqCT2GoZ',
    'campus_code' => 'FPTUHN',
    'date' => '2026-03-18 00:00:00',
    'client_code' => 'HC_SWB',
];

try {
    $result = PushDebtToDngClient::checkPaidOfDay($requestData);

    echo 'HTTP Status: '.$result['status'].PHP_EOL;
    echo 'Payload sent:'.PHP_EOL;
    print_r($result['payload']);
    echo 'Response:'.PHP_EOL;
    echo $result['response'].PHP_EOL;
} catch (Throwable $throwable) {
    echo 'Request failed: '.$throwable->getMessage().PHP_EOL;
}
