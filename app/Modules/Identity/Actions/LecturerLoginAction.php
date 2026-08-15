<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\LoginPipeline;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Auth\AuthenticationException;

class LecturerLoginAction
{
    /**
     * Authenticate a lecturer and return an access token.
     *
     * @param  array  $data  {email, password, device_name, remember_me}
     * @return array {token, lecturer}
     *
     * @throws AuthenticationException
     */
    public static function run(array $data): array
    {
        $user = LoginPipeline::authenticateByPassword($data['email'], $data['password']);
        LoginPipeline::verifyAccountActive($user);

        // Lecturer authorization is strictly Identity-owned. Faculty Workforce
        // synchronously maintains this grant through its narrow command contract.
        $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
        if ($grant === null) {
            throw new AuthenticationException('Account access restricted. Please contact HR.');
        }

        $user->update(['last_login_at' => now()]);

        // The compatibility adapter issues the token to the existing Lecturer
        // authenticatable without exposing Faculty persistence to Identity.
        $deviceName = $data['device_name'] ?? 'Lecturer Device';
        $issued = app(LecturerTokenIssuer::class)->issue($grant->lecturerId, $deviceName);

        return [
            'token' => $issued['token'],
            'lecturer' => $issued['lecturer'],
        ];
    }
}
