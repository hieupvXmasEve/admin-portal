<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use Google\Client as GoogleClient;
use Google\Exception as GoogleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared login-flow steps for the 8 Identity token actions (parent, student,
 * lecturer x password/google/refresh). Every failure throws
 * AuthenticationException with a standardized message — safe for both the
 * blanket `catch (\Exception)` controllers (Parent, Student) and Lecturer's
 * type-discriminated catch (`instanceof AuthenticationException` -> 401).
 */
final class LoginPipeline
{
    public static function checkRateLimit(string $key, int $maxAttempts = 5): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new AuthenticationException("Too many login attempts. Try again in {$seconds} seconds.");
        }
    }

    public static function recordFailedAttempt(string $key, int $decaySeconds = 900): void
    {
        RateLimiter::hit($key, $decaySeconds);
    }

    public static function clearRateLimit(string $key): void
    {
        RateLimiter::clear($key);
    }

    public static function authenticateByPassword(string $email, string $password, ?string $rateLimitKey = null): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($rateLimitKey !== null) {
                self::recordFailedAttempt($rateLimitKey);
            }

            throw new AuthenticationException('Invalid credentials.');
        }

        return $user;
    }

    public static function verifyAccountActive(User $user): void
    {
        if (! $user->isActive()) {
            throw new AuthenticationException('Account is not active. Please contact administration.');
        }
    }

    /** @return array{email: string, email_verified: bool, sub: string, picture?: string} */
    public static function verifyGoogleIdToken(string $idToken): array
    {
        try {
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($idToken);

            if (! $payload) {
                throw new AuthenticationException('Invalid Google ID token.');
            }

            $email = $payload['email'] ?? null;

            if (! $email) {
                throw new AuthenticationException('Unable to retrieve email from Google account.');
            }

            if (! ($payload['email_verified'] ?? false)) {
                throw new AuthenticationException('Google account email is not verified.');
            }

            return $payload;
        } catch (GoogleException|\UnexpectedValueException $e) {
            throw new AuthenticationException('Google authentication failed: '.$e->getMessage());
        }
    }

    public static function resolveGoogleUser(array $payload): User
    {
        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            throw new AuthenticationException('No account found with this email address.');
        }

        return $user;
    }

    /** @param array{sub: string} $payload */
    public static function syncOauthProvider(User $user, array $payload): void
    {
        if (! $user->oauth_provider_id) {
            $user->update([
                'oauth_provider' => 'google',
                'oauth_provider_id' => $payload['sub'],
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
        }
    }
}
