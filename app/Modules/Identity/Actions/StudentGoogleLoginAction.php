<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\LoginPipeline;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Auth\AuthenticationException;

class StudentGoogleLoginAction
{
    /**
     * @throws AuthenticationException
     */
    public static function run(string $idToken, string $ip, ?string $deviceName = null): array
    {
        $payload = LoginPipeline::verifyGoogleIdToken($idToken);
        $user = LoginPipeline::resolveGoogleUser($payload);
        LoginPipeline::verifyAccountActive($user);

        $student = $user->student;

        if (! $student) {
            throw new AuthenticationException('This account is not associated with a student profile.');
        }

        LoginPipeline::syncOauthProvider($user, $payload);

        // Update avatar if needed (profile specific)
        if (! $student->avatar_url && ($payload['picture'] ?? null)) {
            $student->update(['avatar_url' => $payload['picture']]);
        }

        $lifecycleStatus = app(ProgramEnrollmentReader::class)
            ->forStudentId((int) $student->id)
            ->legacyCompatibleStatus();

        // Academic holds remain an explicit access policy. Authentication
        // itself is controlled by Account Status, independently of enrollment.
        $blockingHolds = $student->academicHolds()
            ->where('status', 'active')
            ->where('hold_category', 'all')
            ->exists();

        if ($blockingHolds) {
            throw new AuthenticationException('Account access is restricted due to academic holds.');
        }

        $student->update(['last_login_at' => now()]);

        $deviceName = $deviceName ?? 'Student Portal (Google)';
        $expiresAt = now()->addHours(8);
        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

        return [
            'student' => [
                'id' => $student->id,
                'user_id' => $user->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'status' => $lifecycleStatus,
                'campus' => $student->campus,
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
