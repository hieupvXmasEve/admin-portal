<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\Lecture;
use Illuminate\Validation\ValidationException;

class LecturerRefreshTokenAction
{
    /**
     * @throws ValidationException
     */
    public static function run(Lecture $lecturer, ?string $deviceName = null): array
    {
        // 1. Verify associated User is active
        $user = $lecturer->user;
        if (!$user || !$user->isActive()) {
            throw ValidationException::withMessages([
                'token' => ['Account is inactive. Please contact administration.'],
            ]);
        }

        // 2. Verify Lecturer profile is active
        if (!$lecturer->is_active) {
            throw ValidationException::withMessages([
                'token' => ['Lecturer account is inactive. Please contact administration.'],
            ]);
        }

        // 3. Check employment status
        if (!in_array($lecturer->employment_status, ['active', 'employed', 'contract_active'])) {
            throw ValidationException::withMessages([
                'token' => ['Account access restricted. Please contact HR.'],
            ]);
        }

        $deviceName = $deviceName ?? 'Lecturer Portal';
        $expirationMinutes = 8 * 60;
        $expiresAt = now()->addHours(8);

        $token = $lecturer->createToken($deviceName, ['lecturer:access'], $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes,
        ];
    }
}
