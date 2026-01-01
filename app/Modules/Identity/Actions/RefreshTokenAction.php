<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\Student;
use Exception;

class RefreshTokenAction
{
    /**
     * @throws Exception
     */
    public static function run(Student $student, ?string $deviceName = null): array
    {
        // 1. Verify associated User is active
        $user = $student->user;
        if (!$user || !$user->isActive()) {
            throw new Exception('Account is not active. Please contact administration.');
        }

        // 2. Verify Student profile is active
        if (!$student->isActive()) {
            throw new Exception('Student account is not active. Please contact administration.');
        }

        $deviceName = $deviceName ?? 'Student Portal';
        $expiresAt = now()->addHours(8);

        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}
