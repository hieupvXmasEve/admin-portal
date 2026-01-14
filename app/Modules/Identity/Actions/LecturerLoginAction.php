<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
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

        // 4. Retrieve Associated Lecturer Profile
        $lecturer = $user->lecturer;

        if (! $lecturer) {
            throw ValidationException::withMessages([
                'email' => ['This account is not associated with a lecturer profile.'],
            ]);
        }

        // 5. Verify Lecturer Status (Legacy Rules)
        if (! $lecturer->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Lecturer account is inactive. Please contact administration.'],
            ]);
        }

        // Check employment status
        if (! in_array($lecturer->employment_status, ['active', 'employed', 'contract_active'])) {
            throw ValidationException::withMessages([
                'email' => ['Account access restricted. Please contact HR.'],
            ]);
        }

        // 6. Update Last Login
        $user->update(['last_login_at' => now()]);

        // 7. Generate Token
        // note: We issue the token to the Lecturer model to maintain compatibility
        // with existing routes that expect $request->user() to be a Lecture instance.
        $deviceName = $data['device_name'] ?? 'Lecturer Device';
        $expiresAt = ($data['remember_me'] ?? false) ? now()->addDays(30) : now()->addHours(8);

        $token = $lecturer->createToken(
            $deviceName,
            ['lecturer:access'],
            $expiresAt
        )->plainTextToken;

        return [
            'token' => $token,
            'lecturer' => $lecturer,
        ];
    }
}
