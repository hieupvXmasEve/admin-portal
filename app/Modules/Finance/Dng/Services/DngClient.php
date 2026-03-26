<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DngClient
{
    private string $baseUrl;

    private string $accessCode;

    private string $apiCode;

    private string $clientCode;

    private string $login;

    private int $timeout;

    public function __construct(
        protected DngChecksumService $checksumService,
    ) {
        $this->baseUrl = (string) config('services.dng.base_url');
        $this->accessCode = (string) config('services.dng.access_code');
        $this->apiCode = (string) config('services.dng.api_code');
        $this->clientCode = (string) config('services.dng.client_code');
        $this->login = (string) config('services.dng.login');
        $this->timeout = (int) config('services.dng.timeout', 30);
    }

    /**
     * Push a debt/payment record to DNG.
     *
     * @param  array{
     *     student_code: string,
     *     campus_code: string,
     *     type: string,
     *     amount: int|float|string,
     *     item_id: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     cccd?: string|null,
     * }  $data
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function insertNewRecord(array $data): array
    {
        $amount = (string) $data['amount'];
        $studentId = (string) $data['student_code'];
        $campusCode = $data['campus_code'];
        $itemId = $data['item_id'];

        $checksumString = $this->accessCode
            .$this->apiCode
            .$campusCode
            .$amount
            .$itemId
            .mb_strtolower($studentId, 'UTF-8');

        $payload = [
            'ApiCode' => $this->apiCode,
            'StudentId' => $studentId,
            'CampusCode' => $campusCode,
            'Type' => $data['type'],
            'Amount' => is_numeric($data['amount']) ? $data['amount'] + 0 : $data['amount'],
            'ItemId' => $itemId,
            'Login' => $this->login,
            'CheckSum' => $this->checksumService->generate($checksumString),
            'StudentName' => $data['student_name'],
            'Email' => $data['email'],
            'EstimateTime' => $data['estimate_time'],
            'StudentAddress' => $data['student_address'],
            'CCCD' => $data['cccd'] ?? '',
        ];

        return $this->post('/api/apiv2/InsertNewRecord', $payload);
    }

    /**
     * Get QR / virtual account for a student by fee types.
     *
     * @param  array{
     *     student_code: string,
     *     campus_code: string,
     *     fee_types: array<int, string>,
     * }  $data
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function createVirtualAccountByFeeType(array $data): array
    {
        $studentCode = $data['student_code'];

        $checksumString = mb_strtolower(
            $this->accessCode.$this->apiCode.$studentCode,
            'UTF-8',
        );

        $payload = [
            'StudentCode' => $studentCode,
            'ApiCode' => $this->apiCode,
            'CampusCode' => $data['campus_code'],
            'FeeTypes' => array_values($data['fee_types']),
            'CheckSum' => $this->checksumService->generate($checksumString),
        ];

        return $this->post('/api/dng/createvirtualaccountbyfeetype', $payload);
    }

    /**
     * Check paid transactions for a given campus and date.
     *
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function checkPaidOfDay(string $campusCode, string $date): array
    {
        $checksumString = $campusCode.$this->accessCode.$date;

        $payload = [
            'CampusCode' => $campusCode,
            'Date' => $date,
            'ClientCode' => $this->clientCode,
            'CheckSum' => $this->checksumService->generate($checksumString),
        ];

        return $this->post('/api/checkpaid/checkpaidofday', $payload);
    }

    /**
     * Send a POST request to DNG.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException|ConnectionException
     */
    private function post(string $endpoint, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/').$endpoint;

        Log::channel('stack')->info('DNG API request', [
            'endpoint' => $endpoint,
            'payload_keys' => array_keys($payload),
        ]);

        $response = Http::timeout($this->timeout)
            ->retry(2, 1000, throw: false)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::channel('stack')->error('DNG API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                "DNG API request to {$endpoint} failed with status {$response->status()}: {$response->body()}"
            );
        }

        $decoded = $response->json();

        // DNG returns error codes in body even with HTTP 200
        if (isset($decoded['Code']) && (int) $decoded['Code'] >= 400) {
            Log::channel('stack')->warning('DNG API business error', [
                'endpoint' => $endpoint,
                'code' => $decoded['Code'],
                'message' => $decoded['Message'] ?? 'Unknown error',
            ]);

            throw new RuntimeException(
                "DNG API error [{$decoded['Code']}]: ".($decoded['Message'] ?? 'Unknown error')
            );
        }

        return $decoded;
    }
}
