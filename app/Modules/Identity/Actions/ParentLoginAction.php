<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Http\Resources\Identity\ParentResource;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ParentLoginAction
{
    /**
     * @throws Exception
     */
    public static function run(array $data): array
    {
        $ip = $data['ip'] ?? request()->ip();
        $key = 'login:'.$ip;

        // Check rate limiting
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw new Exception("Too many login attempts. Try again in {$seconds} seconds.");
        }

        // 1. Find User by email
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw new Exception('Invalid credentials');
        }

        // 2. Verify User is active
        if (! $user->isActive()) {
            throw new Exception('Account is not active. Please contact administration.');
        }

        // 3. Verify User is a parent
        if (! $user->isParent()) {
            throw new Exception('This account is not authorized for the parent portal.');
        }

        // 4. Retrieve Associated Parent Profile
        $parentProfile = $user->parentProfile;

        if (! $parentProfile) {
            throw new Exception('Parent profile not found.');
        }

        // 5. Verify Parent Profile is active
        if ($parentProfile->status !== 'active') {
            throw new Exception('Parent account is not active. Please contact administration.');
        }

        $accessGrantReader = app(GuardianAccessGrantReader::class);
        if (! $accessGrantReader->hasAnyActiveGrant((int) $user->id)) {
            throw new Exception('Guardian access has been revoked.');
        }

        RateLimiter::clear($key);

        $user->update(['last_login_at' => now()]);

        $deviceName = $data['device_name'] ?? 'Parent Portal';
        $expiresAt = now()->addHours(8);

        // 6. Create Token with 'parent' ability
        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        $activeStudentIds = $accessGrantReader->activeStudentIdsForUser((int) $user->id);
        $parentProfile->load(['students' => fn ($students) => $students->whereKey($activeStudentIds)]);

        return [
            'parent' => (new ParentResource($parentProfile))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}
