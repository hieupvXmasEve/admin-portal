<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class StudentLoginAction
{
    /**
     * @throws Exception
     */
    public static function run(array $data, string $ip): array
    {
        $key = 'login:' . $ip;

        // Check rate limiting
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw new Exception("Too many login attempts. Try again in {$seconds} seconds.");
        }

        // 1. Find User by email (Single Source of Truth)
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw new Exception('Invalid credentials');
        }

        // 2. Verify User is active
        if (!$user->isActive()) {
            throw new Exception('Account is not active. Please contact administration.');
        }

        // 3. Retrieve Associated Student Profile
        $student = $user->student;

        if (!$student) {
            throw new Exception('This account is not associated with a student profile.');
        }

        // 4. Verify Student is active
        if (!$student->isActive()) {
            throw new Exception('Student account is not active. Please contact administration.');
        }

        // 5. Check for blocking academic holds
        $blockingHolds = $student->academicHolds()
            ->where('status', 'active')
            ->where('hold_category', 'all')
            ->exists();

        if ($blockingHolds) {
            throw new Exception('Account access is restricted due to academic holds.');
        }

        RateLimiter::clear($key);

        $student->update(['last_login_at' => now()]);

        $deviceName = $data['device_name'] ?? 'Student Portal';
        $expiresAt = ($data['remember_me'] ?? false) ? now()->addDays(30) : now()->addHours(8);

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
    }
}
