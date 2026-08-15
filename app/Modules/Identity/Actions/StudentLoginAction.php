<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\LoginPipeline;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Auth\AuthenticationException;

class StudentLoginAction
{
    /**
     * @throws AuthenticationException
     */
    public static function run(array $data, string $ip): array
    {
        $key = 'login:'.$ip;
        LoginPipeline::checkRateLimit($key);

        $user = LoginPipeline::authenticateByPassword($data['email'], $data['password'], $key);
        LoginPipeline::verifyAccountActive($user);

        $student = $user->student;

        if (! $student) {
            throw new AuthenticationException('This account is not associated with a student profile.');
        }

        $lifecycleStatus = app(ProgramEnrollmentReader::class)
            ->forStudentId((int) $student->id)
            ->legacyCompatibleStatus();

        // Check for blocking academic holds. Authentication itself is
        // controlled by Account Status, independently of enrollment lifecycle.
        $blockingHolds = $student->academicHolds()
            ->where('status', 'active')
            ->where('hold_category', 'all')
            ->exists();

        if ($blockingHolds) {
            throw new AuthenticationException('Account access is restricted due to academic holds.');
        }

        LoginPipeline::clearRateLimit($key);
        $user->update(['last_login_at' => now()]);

        $deviceName = $data['device_name'] ?? 'Student Portal';
        $expiresAt = now()->addHours(8);

        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

        return [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'user_id' => $user->id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'status' => $lifecycleStatus,
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
