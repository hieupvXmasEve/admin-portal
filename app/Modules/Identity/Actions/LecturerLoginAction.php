<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LecturerLoginAction
{
    /**
     * Authenticate a lecturer and return an access token.
     *
     * @param  array  $data  {email, password, device_name, remember_me}
     * @return array {token, lecturer}
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public static function run(array $data): array
    {
        // 1. Find User by email (Single Source of Truth)
        $user = User::where('email', $data['email'])->first();

        // 2. Verify User Credentials
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        // 3. Verify User Status
        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Account is inactive. Please contact administration.'],
            ]);
        }

        // Lecturer authorization is strictly Identity-owned. Faculty Workforce
        // synchronously maintains this grant through its narrow command contract.
        $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
        if ($grant === null) {
            throw ValidationException::withMessages([
                'email' => ['Account access restricted. Please contact HR.'],
            ]);
        }

        // 4. Update Last Login
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
