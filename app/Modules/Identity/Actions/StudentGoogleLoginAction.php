<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\Student;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class StudentGoogleLoginAction
{
    /**
     * @throws Exception
     */
    public static function run(string $idToken, string $ip, ?string $deviceName = null, bool $rememberMe = false): array
    {
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

            // 1. Find User by email (Single Source of Truth)
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw new Exception('No account found with this email address');
            }

            // 2. Verify User Status
            if (!$user->isActive()) {
                throw new Exception('Account is inactive. Please contact administration.');
            }

            // 3. Retrieve Associated Student Profile
            $student = $user->student;

            if (!$student) {
                throw new Exception('This account is not associated with a student profile.');
            }

            // Update OAuth provider data if not already set on the User
            if (!$user->oauth_provider_id) {
                $user->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $payload['sub'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            }

            // Update avatar if needed (profile specific)
            if (!$student->avatar_url && ($payload['picture'] ?? null)) {
                $student->update(['avatar_url' => $payload['picture']]);
            }

            // Student active check
            if (!$student->isActive()) {
                throw new Exception('Student account is not active. Please contact administration.');
            }

            // Check for blocking academic holds
            $blockingHolds = $student->academicHolds()
                ->where('status', 'active')
                ->where('hold_category', 'all')
                ->exists();

            if ($blockingHolds) {
                throw new Exception('Account access is restricted due to academic holds.');
            }

            $student->update(['last_login_at' => now()]);

            $deviceName = $deviceName ?? 'Student Portal (Google)';
            $expiresAt = $rememberMe ? now()->addDays(30) : now()->addDays(30);
            $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

            return [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                    'campus' => $student->campus?->name,
                    'program' => $student->program?->name,
                    'specialization' => $student->specialization?->name,
                    'avatar_url' => $student->avatar_url,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ];

        } catch (\Google\Exception $e) {
            throw new Exception('Google authentication failed: ' . $e->getMessage());
        }
    }
}
