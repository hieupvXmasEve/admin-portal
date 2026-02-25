<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Exception;

class ParentRefreshTokenAction
{
    /**
     * @throws Exception
     */
    public static function run(User $user, ?string $deviceName = null): array
    {
        if (! $user->isActive() || ! $user->isParent()) {
            throw new Exception('Parent account is not active.');
        }

        $parentProfile = $user->parentProfile;
        if (! $parentProfile || $parentProfile->status !== 'active') {
            throw new Exception('Parent profile is not active.');
        }

        $deviceName = $deviceName ?? 'Parent Portal';
        $expiresAt = now()->addHours(8);

        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}

