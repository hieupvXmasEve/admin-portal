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
        // Match existing AuthController expiration logic (525600 minutes / 1 year default in sanctum config, or custom)
        // The legacy controller used config('sanctum.expiration', 525600) for response but didn't set it on createToken explicitly (defaulting to config).
        // Let's stick to a reasonable default or configuration.
        // Legacy login set it to 30 days (remember) or 8 hours.
        // Legacy refresh didn't specify expiration on createToken, so it used sanctum.expiration.

        $expirationMinutes = config('sanctum.expiration', 525600);
        $expiresAt = now()->addMinutes($expirationMinutes);

        $token = $lecturer->createToken($deviceName, ['lecturer:access'], $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes,
        ];
    }
}
