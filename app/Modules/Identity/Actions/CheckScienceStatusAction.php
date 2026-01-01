<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CheckScienceStatusAction
{
    /**
     * Check if lecturer is Science
     *
     * @throws Exception
     */
    public static function run(string $email, string $employeeId): array
    {
        try {
            // Generate checksum URL
            $url = self::generateScienceCheckUrl($email, $employeeId);

            // Make HTTP request
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw new Exception('Failed to check Science status');
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Failed to check Science status', [
                'email' => $email,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate Science check URL with checksum
     */
    private static function generateScienceCheckUrl(string $email, string $employeeId): string
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $scienceHashCode = 'aba51784ad6cf5a6ec1b69e02c3b00f79f5d32d87ea20a558be7eec1fb6bd624';

        // Equivalent: email + "FAP" + dd/MM/yyyy HH:00
        $value = $email . 'FAP' . date('d/m/Y H:00');
        $checksum = self::getCheckSum($value, $scienceHashCode);

        return "https://hr.fpt.edu.vn/Science/CheckIsScience?email={$email}&employeeId={$employeeId}&checksum={$checksum}";
    }

    /**
     * Generate checksum using HMAC-SHA1 and Base64 encoding
     */
    private static function getCheckSum(string $value, string $hashKey): string
    {
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $value, $hashKey, true);

        // Base64 encode
        $checksum = base64_encode($hash);

        // Replace
        $checksum = str_replace('=', '%3d', $checksum);
        $checksum = str_replace(' ', '+', $checksum);

        return $checksum;
    }
}
