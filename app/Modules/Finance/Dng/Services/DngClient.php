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
     * @param  array<string, mixed>|null  $payload
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function insertNewRecord(array $data, ?array $payload = null): array
    {
        $payload ??= $this->buildInsertNewRecordPayload($data);

        return $this->post('/api/apiv2/InsertNewRecord', $payload);
    }

    /**
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
     * @return array<string, mixed>
     */
    public function buildInsertNewRecordPayload(array $data): array
    {
        // DNG recomputes the checksum from the Amount it receives, so the value
        // signed here and the value transmitted below must be identical. Callers
        // that read a decimal-cast attribute hand us "17780000.00" while the
        // payload sends 17780000 — normalise once and use it for both.
        $amount = is_numeric($data['amount']) ? $data['amount'] + 0 : $data['amount'];
        $studentId = (string) $data['student_code'];
        $campusCode = $data['campus_code'];
        $itemId = $data['item_id'];

        $checksumString = $this->accessCode
            .$this->apiCode
            .$campusCode
            .(string) $amount
            .$itemId
            .mb_strtolower($studentId, 'UTF-8');

        return [
            'ApiCode' => $this->apiCode,
            'StudentId' => $studentId,
            'CampusCode' => $campusCode,
            'Type' => $data['type'],
            'Amount' => $amount,
            'ItemId' => $itemId,
            'Login' => $this->login,
            'CheckSum' => $this->checksumService->generate($checksumString),
            'StudentName' => $data['student_name'],
            'Email' => $data['email'],
            'EstimateTime' => $data['estimate_time'],
            'StudentAddress' => $data['student_address'],
            'CCCD' => $data['cccd'] ?? '',
        ];
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
     * Get installment payment link for a student by fee types.
     *
     * @param  array{
     *     student_code: string,
     *     campus_code: string,
     *     fee_types: array<int, string>,
     * }  $data
     * @return array<string, mixed>
     */
    public function createFoxpayPaymentByFeeType(array $data): array
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

        return $this->post('/api/dng/createpayfoxpaybyFeeType', $payload);
    }

    /**
     * Build the payload for a batch insert (multiple records in one request).
     *
     * Batch checksum: accessCode + apiCode + campusCode
     *
     * @param  array<int, array{
     *     student_code: string,
     *     campus_code: string,
     *     type: string,
     *     amount: int|float|string,
     *     item_id: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     note?: string|null,
     *     note_einvoice?: string|null,
     *     cccd?: string|null,
     * }>  $records
     * @return array<string, mixed>
     */
    public function buildBatchInsertPayload(string $campusCode, array $records): array
    {
        $checksumString = $this->accessCode.$this->apiCode.$campusCode;

        return [
            'ApiCode' => $this->apiCode,
            'CampusCode' => $campusCode,
            'CheckSum' => $this->checksumService->generate($checksumString),
            'Records' => array_values(array_map(fn (array $record) => [
                'StudentId' => $record['student_code'],
                'CampusCode' => $campusCode,
                'Type' => $record['type'],
                'Amount' => is_numeric($record['amount']) ? $record['amount'] + 0 : (string) $record['amount'],
                'ItemId' => $record['item_id'],
                'StudentName' => $record['student_name'],
                'Email' => $record['email'],
                'EstimateTime' => $record['estimate_time'],
                'Note' => $record['note'] ?? '',
                'NoteEinvoice' => $record['note_einvoice'] ?? '',
                'StudentAddress' => $record['student_address'],
                'CCCD' => $record['cccd'] ?? '',
            ], $records)),
        ];
    }

    /**
     * Push multiple debt records to DNG in a single batch call.
     *
     * @param  array<int, array{...}>  $records
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function insertBatchRecords(string $campusCode, array $records): array
    {
        $payload = $this->buildBatchInsertPayload($campusCode, $records);

        return $this->post('/api/apiv2/InsertListNewRecord11', $payload);
    }

    /**
     * Cancel a previously-pushed DNG record by re-inserting it with Amount = -1.
     *
     * The DNG system treats a record identical to the original but with a negative
     * amount as a reversal/cancellation of the original charge.
     *
     * @param  array{
     *     student_code: string,
     *     campus_code: string,
     *     type: string,
     *     item_id: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     cccd?: string|null,
     * }  $originalData  Same fields as insertNewRecord but without `amount` (forced to -1)
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function cancelRecord(array $originalData): array
    {
        $payload = $this->buildInsertNewRecordPayload(array_merge($originalData, ['amount' => -1]));

        return $this->post('/api/apiv2/InsertNewRecord', $payload);
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
        $businessCode = $decoded['Code'] ?? $decoded['code'] ?? null;

        if ($businessCode !== null && (int) $businessCode >= 400) {
            Log::channel('stack')->warning('DNG API business error', [
                'endpoint' => $endpoint,
                'code' => $businessCode,
                'message' => $decoded['Message'] ?? $decoded['message'] ?? 'Unknown error',
            ]);

            throw new RuntimeException(
                "DNG API error [{$businessCode}]: ".($decoded['Message'] ?? $decoded['message'] ?? 'Unknown error')
            );
        }

        return $decoded;
    }
}
