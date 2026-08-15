<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\LoginPipeline;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Auth\AuthenticationException;

class LecturerGoogleLoginAction
{
    /**
     * @param  array{id_token: string, ip: string, device_name?: string|null}  $data
     *
     * @throws AuthenticationException
     */
    public static function run(array $data): array
    {
        $payload = LoginPipeline::verifyGoogleIdToken($data['id_token']);
        $user = LoginPipeline::resolveGoogleUser($payload);
        LoginPipeline::verifyAccountActive($user);

        $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
        if ($grant === null) {
            throw new AuthenticationException('Account access restricted. Please contact HR.');
        }

        LoginPipeline::syncOauthProvider($user, $payload);
        $user->update(['last_login_at' => now()]);

        // The existing Lecturer authenticatable remains the Sanctum token
        // subject through an adapter outside Identity's persistence boundary.
        $deviceName = $data['device_name'] ?? 'Lecturer Portal (Google)';
        $issued = app(LecturerTokenIssuer::class)->issue(
            $grant->lecturerId,
            $deviceName,
            $payload['picture'] ?? null,
        );

        return [
            'token' => $issued['token'],
            'lecturer' => $issued['lecturer'],
        ];
    }
}
