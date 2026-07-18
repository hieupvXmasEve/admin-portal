<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Validation\ValidationException;

class LecturerRefreshTokenAction
{
    /**
     * @throws ValidationException
     */
    /** @param array{user_id: int, device_name?: string|null} $data */
    public static function run(array $data): array
    {
        // Refresh authorization reads only Identity-owned Account Status and
        // Lecturer Access Grant. The legacy lecturer token subject is resolved
        // by an infrastructure adapter after authorization succeeds.
        $user = User::query()->find($data['user_id']);
        if (! $user || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'token' => ['Account is inactive. Please contact administration.'],
            ]);
        }

        $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
        if ($grant === null) {
            throw ValidationException::withMessages([
                'token' => ['Account access restricted. Please contact HR.'],
            ]);
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
