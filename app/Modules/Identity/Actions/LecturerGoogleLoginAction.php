<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Google\Client;
use Illuminate\Validation\ValidationException;
use Exception;

class LecturerGoogleLoginAction
{
    /**
     * @throws ValidationException
     * @throws Exception
     */
    public static function run(string $idToken, string $ip, ?string $deviceName = null): array
    {
        try {
            // Initialize Google Client
            $client = new Client([
                'client_id' => config('services.google.client_id'),
            ]);

            // Verify the ID token
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                throw ValidationException::withMessages([
                    'id_token' => ['Invalid Google ID token'],
                ]);
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                throw ValidationException::withMessages([
                    'email' => ['Unable to retrieve email from Google account'],
                ]);
            }

            if (!($payload['email_verified'] ?? false)) {
                throw ValidationException::withMessages([
                    'email' => ['Google account email is not verified'],
                ]);
            }

            // 1. Find User by email (Single Source of Truth)
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['No account found with this email address.'],
                ]);
            }

            // 2. Verify User Status
            if (!$user->isActive()) {
                throw ValidationException::withMessages([
                    'email' => ['Account is inactive. Please contact administration.'],
                ]);
            }

            // 3. Retrieve Associated Lecturer Profile
            $lecturer = $user->lecturer;

            if (!$lecturer) {
                throw ValidationException::withMessages([
                    'email' => ['This account is not associated with a lecturer profile.'],
                ]);
            }

            // 4. Update OAuth provider data on User model
            if (!$user->oauth_provider_id) {
                $user->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $payload['sub'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            }

            // Update avatar if needed (profile specific)
            if (!$lecturer->avatar_url && ($payload['picture'] ?? null)) {
                $lecturer->update(['avatar_url' => $payload['picture']]);
            }

            // 5. Check Lecturer Status
            if (!$lecturer->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Lecturer account is inactive. Please contact administration.'],
                ]);
            }

            // Check employment status
            if (!in_array($lecturer->employment_status, ['active', 'employed', 'contract_active'])) {
                throw ValidationException::withMessages([
                    'email' => ['Account access restricted. Please contact HR.'],
                ]);
            }

            // 6. Update Last Login
            $user->update(['last_login_at' => now()]);

            // 7. Generate Token
            $deviceName = $deviceName ?? 'Lecturer Portal (Google)';
            $expiresAt = now()->addHours(8);

            $token = $lecturer->createToken($deviceName, ['lecturer:access'], $expiresAt)->plainTextToken;

            return [
                'token' => $token,
                'lecturer' => $lecturer,
            ];
        } catch (\Google\Exception $e) {
            throw new Exception('Google authentication failed: ' . $e->getMessage());
        }
    }
}
