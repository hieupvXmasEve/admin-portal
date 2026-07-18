<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Exception;
use Google\Client;
use Illuminate\Validation\ValidationException;

class LecturerGoogleLoginAction
{
    /**
     * @throws ValidationException
     * @throws Exception
     */
    /** @param array{id_token: string, ip: string, device_name?: string|null} $data */
    public static function run(array $data): array
    {
        try {
            // Initialize Google Client
            $client = new Client([
                'client_id' => config('services.google.client_id'),
            ]);

            // Verify the ID token
            $payload = $client->verifyIdToken($data['id_token']);

            if (! $payload) {
                throw ValidationException::withMessages([
                    'id_token' => ['Invalid Google ID token'],
                ]);
            }

            $email = $payload['email'] ?? null;
            if (! $email) {
                throw ValidationException::withMessages([
                    'email' => ['Unable to retrieve email from Google account'],
                ]);
            }

            if (! ($payload['email_verified'] ?? false)) {
                throw ValidationException::withMessages([
                    'email' => ['Google account email is not verified'],
                ]);
            }

            // 1. Find User by email (Single Source of Truth)
            $user = User::where('email', $email)->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => ['No account found with this email address.'],
                ]);
            }

            // 2. Verify User Status
            if (! $user->isActive()) {
                throw ValidationException::withMessages([
                    'email' => ['Account is inactive. Please contact administration.'],
                ]);
            }

            $grant = app(ApiActorPolicy::class)->lecturerAccessFor($user);
            if ($grant === null) {
                throw ValidationException::withMessages([
                    'email' => ['Account access restricted. Please contact HR.'],
                ]);
            }

            // 4. Update OAuth provider data on User model
            if (! $user->oauth_provider_id) {
                $user->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $payload['sub'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            }

            // 4. Update Last Login
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
        } catch (\Google\Exception $e) {
            throw new Exception('Google authentication failed: '.$e->getMessage());
        }
    }
}
