<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\Identity\DTO\ImpersonationTokenResult;
use App\Shared\Contracts\StudentRegistry\StudentImpersonationTokenIssuer;
use Illuminate\Support\Facades\Log;

final class EloquentStudentImpersonationTokenIssuer implements StudentImpersonationTokenIssuer
{
    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    public function issue(string $identifier, ImpersonationAdministrator $administrator): ImpersonationTokenResult
    {
        $student = Student::query()->where('email', $identifier)->orWhere('student_id', $identifier)->first();
        if ($student === null) {
            throw new \DomainException('Student not found with the provided email or student ID.');
        }
        $status = $this->lifecycleStatuses->statusesFor([(int) $student->id])[(int) $student->id] ?? $student->status;
        if (! $student->isActive()) {
            throw new \InvalidArgumentException('Cannot impersonate inactive student. Student status: '.$status);
        }
        if ($student->academicHolds()->where('status', 'active')->where('hold_category', 'all')->exists()) {
            throw new \InvalidArgumentException('Cannot impersonate student due to active academic holds that restrict access.');
        }

        $deviceName = $administrator->deviceName ?? 'Admin Impersonation';
        $expiresAt = now()->addHours(2);
        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;
        Log::info('Admin student impersonation', ['admin_id' => $administrator->id, 'admin_email' => $administrator->email, 'student_id' => $student->id, 'student_email' => $student->email, 'student_student_id' => $student->student_id, 'device_name' => $deviceName, 'expires_at' => $expiresAt->toISOString(), 'ip_address' => $administrator->ipAddress, 'user_agent' => $administrator->userAgent]);

        return new ImpersonationTokenResult(['student' => ['id' => $student->id, 'student_id' => $student->student_id, 'user_id' => $student->user_id, 'full_name' => $student->full_name, 'email' => $student->email, 'status' => $status, 'campus' => $student->campus?->name, 'program' => $student->program?->name, 'specialization' => $student->specialization?->name, 'avatar_url' => $student->avatar_url], 'token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expiresAt->toISOString(), 'impersonation_info' => ['impersonated_by' => ['id' => $administrator->id, 'name' => $administrator->name, 'email' => $administrator->email], 'impersonated_at' => now()->toISOString(), 'purpose' => $administrator->purpose ?? 'Admin support']]);
    }
}
