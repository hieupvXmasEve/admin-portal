<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\LoginPipeline;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Auth\AuthenticationException;

class LecturerRefreshTokenAction
{
    /**
     * @param  array{user_id: int, device_name?: string|null}  $data
     *
     * @throws AuthenticationException
     */
    public static function run(array $data): array
    {
        // Refresh authorization reads only Identity-owned Account Status and
        // Lecturer Access Grant. The legacy lecturer token subject is resolved
        // by an infrastructure adapter after authorization succeeds.
        $user = User::query()->find($data['user_id']);
        if (! $user) {
            throw new AuthenticationException('Account is not active. Please contact administration.');
        }
        LoginPipeline::verifyAccountActive($user);

        $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
        if ($grant === null) {
            throw new AuthenticationException('Account access restricted. Please contact HR.');
        }

        $deviceName = $data['device_name'] ?? 'Lecturer Portal';
        $expirationMinutes = 8 * 60;
        $issued = app(LecturerTokenIssuer::class)->issue($grant->lecturerId, $deviceName);

        return [
            'token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes,
        ];
    }
}
