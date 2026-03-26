<?php

declare(strict_types=1);

require_once __DIR__.'/GetCheckSum.php';
require_once __DIR__.'/push-debt-to-dng.php';

$requestData = [
    'access_code' => 'lDf0pQO8ODS4V61JAkV833dRSix1IU4BBcdtvKst82wF4TuwyfwYRXpsAyqhtYy4KqCT2GoZ',
    'student_id' => 'SWB001',
    'campus_code' => 'FSWHN',
    'type' => 'HP',
    'amount' => 10000,
    'item_id' => '1234',
    'student_name' => 'Truong Ngoc Han',
    'email' => 'nguyenvana@fpt.edu.vn',
    'estimate_time' => '05/26',
    'student_address' => 'Cai Rang, Can Tho',
    'cccd' => '123456789123',
];

try {
    $result = PushDebtToDngClient::push($requestData);

    echo 'HTTP Status: '.$result['status'].PHP_EOL;
    echo 'Payload sent:'.PHP_EOL;
    print_r($result['payload']);
    echo 'Response:'.PHP_EOL;
    echo $result['response'].PHP_EOL;
} catch (Throwable $throwable) {
    echo 'Request failed: '.$throwable->getMessage().PHP_EOL;
}
