<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;

class DngChecksumService
{
    private string $hashKey;

    private string $accessCode;

    private string $clientCode;

    public function __construct()
    {
        $this->hashKey = (string) config('services.dng.hash_key');
        $this->accessCode = (string) config('services.dng.access_code');
        $this->clientCode = (string) config('services.dng.client_code');
    }

    /**
     * Generate a DNG checksum from the given value string.
     *
     * Uses HMAC-SHA1 + base64, then replaces `=` with `%3d` and space with `+`.
     */
    public function generate(string $value): string
    {
        $hash = hash_hmac('sha1', $value, $this->hashKey, true);

        return str_replace(
            ['=', ' '],
            ['%3d', '+'],
            base64_encode($hash),
        );
    }

    /**
     * Verify a DNG checksum matches the expected value.
     */
    public function verify(string $value, string $expectedChecksum): bool
    {
        return hash_equals($this->generate($value), $expectedChecksum);
    }

    /**
     * Verify webhook callback checksum using original request values from dng_payment_requests.
     *
     * Formula: AccessCode + ClientCode + Amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
     * Values come from the original push request (stored in dng_payment_requests),
     * except InvoiceSerialNumber which comes from the webhook callback payload.
     */
    public function verifyWebhookChecksum(DngPaymentRequest $dngRequest, array $webhookPayload): bool
    {
        $pushPayload = is_array($dngRequest->push_payload) ? $dngRequest->push_payload : [];

        $invoiceSerialNumber = (string) ($webhookPayload['InvoiceSerialNumber'] ?? '');
        $studentId = (string) ($pushPayload['StudentId'] ?? $dngRequest->student_code);
        $feeType = (string) ($pushPayload['Type'] ?? $pushPayload['FeeType'] ?? $dngRequest->fee_type);
        $campusCode = (string) ($pushPayload['CampusCode'] ?? $dngRequest->campus_code);
        $expectedChecksum = (string) ($webhookPayload['CheckSum'] ?? '');

        foreach ($this->webhookAmountCandidates($pushPayload['Amount'] ?? $dngRequest->amount) as $amount) {
            $checksumValue = $this->accessCode
                .$this->clientCode
                .$amount
                .$invoiceSerialNumber
                .$studentId
                .$feeType
                .$campusCode;

            if ($this->verify($checksumValue, $expectedChecksum)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function webhookAmountCandidates(mixed $amount): array
    {
        $raw = (string) $amount;
        if (! is_numeric($raw)) {
            return [$raw];
        }

        $numeric = (float) $raw;

        return array_values(array_unique([
            $raw,
            (string) ($numeric + 0),
            number_format($numeric, 1, '.', ''),
            number_format($numeric, 2, '.', ''),
        ]));
    }
}
