<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Models\ParentProfile;
use App\Modules\Identity\Http\Resources\Identity\ParentResource;
use Google\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class ParentGoogleLoginAction
{
    /**
     * @throws Exception
     */
    public static function run(array $data): array
    {
        $idToken = $data['id_token'];
        $ip = $data['ip'] ?? request()->ip();
        $deviceName = $data['device_name'] ?? 'Parent Portal (Google)';
        $rememberMe = $data['remember_me'] ?? false;

        $key = 'google-login:' . $ip;

        try {
            // Initialize Google Client
            $client = new Client([
                'client_id' => config('services.google.client_id'),
            ]);

            // Verify the ID token
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                throw new Exception('Invalid Google ID token');
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                throw new Exception('Unable to retrieve email from Google account');
            }

            if (!($payload['email_verified'] ?? false)) {
                throw new Exception('Google account email is not verified');
            }

            // 1. Find User by email
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw new Exception('No account found with this email address');
            }

            // 2. Verify User is active
            if (!$user->isActive()) {
                throw new Exception('Account is not active. Please contact administration.');
            }

            // 3. Verify User is a parent
            if (!$user->isParent()) {
                throw new Exception('This account is not authorized for the parent portal.');
            }

            // 4. Retrieve Associated Parent Profile
            $parentProfile = $user->parentProfile;

            if (!$parentProfile) {
                throw new Exception('Parent profile not found.');
            }

            // 5. Verify Parent Profile is active
            if ($parentProfile->status !== 'active') {
                throw new Exception('Parent account is not active. Please contact administration.');
            }

            // Update OAuth provider data if not already set on the User
            if (!$user->oauth_provider_id) {
                $user->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $payload['sub'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            }

            $user->update(['last_login_at' => now()]);

            $deviceName = $deviceName ?? 'Parent Portal (Google)';
            $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(8);

            // 6. Create Token with 'parent' ability
            $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

            // Load children for the resource
            $parentProfile->load('students');

            return [
                'parent' => (new ParentResource($parentProfile))->resolve(),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ];

        } catch (\Google\Exception $e) {
            throw new Exception('Google authentication failed: ' . $e->getMessage());
        }
    }
}
