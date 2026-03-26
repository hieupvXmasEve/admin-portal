<?php

declare(strict_types=1);

require_once __DIR__.'/GetCheckSum.php';

final class PushDebtToDngClient
{
    private const PUSH_DEBT_ENDPOINT = 'https://googleauthensite02.fpt.edu.vn:90/api/apiv2/InsertNewRecord';

    private const PULL_QR_ENDPOINT = 'https://googleauthensite02.fpt.edu.vn:90/api/dng/createvirtualaccountbyfeetype';

    private const CHECK_PAID_OF_DAY_ENDPOINT = 'https://googleauthensite02.fpt.edu.vn:90/api/checkpaid/checkpaidofday';

    private const DEFAULT_API_CODE = 'HC_SWB';

    private const DEFAULT_CLIENT_CODE = 'HC_ASIA';

    private const DEFAULT_LOGIN = 'HC_SWB';

    /**
     * @param array{
     *     access_code: string,
     *     student_id: string,
     *     campus_code: string,
     *     type: string,
     *     amount: int|float|string,
     *     item_id: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     cccd?: string|null,
     *     api_code?: string,
     *     login?: string
     * } $data
     * @return array{status:int,response:string,payload:array<string,mixed>}
     */
    public static function push(array $data): array
    {
        $apiCode = $data['api_code'] ?? self::DEFAULT_API_CODE;
        $login = $data['login'] ?? self::DEFAULT_LOGIN;
        $studentId = $data['student_id'];
        $campusCode = $data['campus_code'];
        $amount = (string) $data['amount'];
        $itemId = $data['item_id'];

        $checksumString = $data['access_code']
            .$apiCode
            .$campusCode
            .$amount
            .$itemId
            .mb_strtolower($studentId, 'UTF-8');

        $payload = [
            'ApiCode' => $apiCode,
            'StudentId' => $studentId,
            'CampusCode' => $campusCode,
            'Type' => $data['type'],
            'Amount' => is_numeric($data['amount']) ? $data['amount'] + 0 : $data['amount'],
            'ItemId' => $itemId,
            'Login' => $login,
            'CheckSum' => GetCheckSum::getCheckSum($checksumString),
            'StudentName' => $data['student_name'],
            'Email' => $data['email'],
            'EstimateTime' => $data['estimate_time'],
            'StudentAddress' => $data['student_address'],
            'CCCD' => $data['cccd'] ?? '',
        ];

        return self::postJson(self::PUSH_DEBT_ENDPOINT, $payload);
    }

    /**
     * @param array{
     *     access_code: string,
     *     student_code: string,
     *     campus_code: string,
     *     fee_types: array<int, string>,
     *     api_code?: string
     * } $data
     * @return array{status:int,response:string,payload:array<string,mixed>}
     */
    public static function pullQr(array $data): array
    {
        $apiCode = $data['api_code'] ?? self::DEFAULT_API_CODE;
        $studentCode = $data['student_code'];

        $checksumString = mb_strtolower(
            $data['access_code'].$apiCode.$studentCode,
            'UTF-8',
        );

        $payload = [
            'StudentCode' => $studentCode,
            'ApiCode' => $apiCode,
            'CampusCode' => $data['campus_code'],
            'FeeTypes' => array_values($data['fee_types']),
            'CheckSum' => GetCheckSum::getCheckSum($checksumString),
        ];

        return self::postJson(self::PULL_QR_ENDPOINT, $payload);
    }

    /**
     * @param array{
     *     access_code: string,
     *     campus_code: string,
     *     date: string,
     *     client_code?: string
     * } $data
     * @return array{status:int,response:string,payload:array<string,mixed>}
     */
    public static function checkPaidOfDay(array $data): array
    {
        $clientCode = $data['client_code'] ?? self::DEFAULT_CLIENT_CODE;
        $campusCode = $data['campus_code'];
        $date = $data['date'];

        $checksumString = $campusCode.$data['access_code'].$date;

        $payload = [
            'CampusCode' => $campusCode,
            'Date' => $date,
            'ClientCode' => $clientCode,
            'CheckSum' => GetCheckSum::getCheckSum($checksumString),
        ];

        return self::postJson(self::CHECK_PAID_OF_DAY_ENDPOINT, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status:int,response:string,payload:array<string,mixed>}
     */
    private static function postJson(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);

        if ($ch === false) {
            throw new RuntimeException('Cannot initialize cURL.');
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: '.strlen($jsonPayload),
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException('DNG API request failed: '.$error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'response' => $response,
            'payload' => $payload,
        ];
    }
}
