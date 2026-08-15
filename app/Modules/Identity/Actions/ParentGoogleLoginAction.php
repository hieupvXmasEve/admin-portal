<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Http\Resources\Identity\ParentResource;
use App\Modules\Identity\Support\LoginPipeline;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Illuminate\Auth\AuthenticationException;

class ParentGoogleLoginAction
{
    /**
     * @throws AuthenticationException
     */
    public static function run(array $data): array
    {
        $payload = LoginPipeline::verifyGoogleIdToken($data['id_token']);
        $user = LoginPipeline::resolveGoogleUser($payload);
        LoginPipeline::verifyAccountActive($user);

        if (! $user->isParent()) {
            throw new AuthenticationException('This account is not authorized for the parent portal.');
        }

        $parentProfile = $user->parentProfile;

        if (! $parentProfile) {
            throw new AuthenticationException('Parent profile not found.');
        }

        if ($parentProfile->status !== 'active') {
            throw new AuthenticationException('Parent account is not active. Please contact administration.');
        }

        $accessGrantReader = app(GuardianAccessGrantReader::class);
        if (! $accessGrantReader->hasAnyActiveGrant((int) $user->id)) {
            throw new AuthenticationException('Guardian access has been revoked.');
        }

        LoginPipeline::syncOauthProvider($user, $payload);
        $user->update(['last_login_at' => now()]);

        $deviceName = $data['device_name'] ?? 'Parent Portal (Google)';
        $expiresAt = now()->addHours(8);
        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        $parentProfile->load('students');

        return [
            'parent' => (new ParentResource($parentProfile))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}
