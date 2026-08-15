<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\LoginPipeline;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Illuminate\Auth\AuthenticationException;

class ParentRefreshTokenAction
{
    /**
     * @throws AuthenticationException
     */
    public static function run(User $user, ?string $deviceName = null): array
    {
        if (! $user->isParent()) {
            throw new AuthenticationException('Parent account is not active.');
        }

        LoginPipeline::verifyAccountActive($user);

        $parentProfile = $user->parentProfile;
        if (! $parentProfile || $parentProfile->status !== 'active') {
            throw new AuthenticationException('Parent profile is not active.');
        }

        if (! app(GuardianAccessGrantReader::class)->hasAnyActiveGrant((int) $user->id)) {
            throw new AuthenticationException('Guardian access has been revoked.');
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
