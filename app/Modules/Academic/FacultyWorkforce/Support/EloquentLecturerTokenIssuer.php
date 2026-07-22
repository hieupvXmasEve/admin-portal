<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Academic\LecturerImpersonationTokenIssuer;
use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\Identity\DTO\ImpersonationTokenResult;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use Illuminate\Support\Facades\Log;

final class EloquentLecturerTokenIssuer implements LecturerImpersonationTokenIssuer, LecturerTokenIssuer
{
    public function issue(int $lecturerId, string $deviceName, ?string $avatarUrl = null): array
    {
        $lecturer = Lecture::query()->find($lecturerId);

        if ($lecturer === null) {
            throw new \DomainException('This account is not associated with a lecturer profile.');
        }

        if ($lecturer->avatar_url === null && $avatarUrl !== null) {
            $lecturer->update(['avatar_url' => $avatarUrl]);
        }

        return [
            'token' => $lecturer->createToken(
                $deviceName,
                ['lecturer:access'],
                now()->addHours(8),
            )->plainTextToken,
            'lecturer' => $lecturer,
        ];
    }

    public function impersonate(string $identifier, ImpersonationAdministrator $administrator): ImpersonationTokenResult
    {
        $lecturer = Lecture::query()->where('email', $identifier)->orWhere('employee_id', $identifier)->first();
        if ($lecturer === null) {
            throw new \DomainException('Lecturer not found with the provided email or employee ID.');
        }
        if (! $lecturer->isActive()) {
            throw new \InvalidArgumentException('Cannot impersonate inactive lecturer. Lecturer status: '.$lecturer->employment_status);
        }

        $deviceName = $administrator->deviceName ?? 'Admin Impersonation';
        $expiresAt = now()->addHours(2);
        $token = $lecturer->createToken($deviceName, ['lecturer'], $expiresAt)->plainTextToken;
        Log::info('Admin lecturer impersonation', ['admin_id' => $administrator->id, 'admin_email' => $administrator->email, 'lecturer_id' => $lecturer->id, 'lecturer_email' => $lecturer->email, 'lecturer_employee_id' => $lecturer->employee_id, 'device_name' => $deviceName, 'expires_at' => $expiresAt->toISOString(), 'ip_address' => $administrator->ipAddress, 'user_agent' => $administrator->userAgent]);

        return new ImpersonationTokenResult(['lecturer' => ['id' => $lecturer->id, 'employee_id' => $lecturer->employee_id, 'display_name' => $lecturer->display_name, 'email' => $lecturer->email, 'employment_status' => $lecturer->employment_status, 'academic_rank' => $lecturer->academic_rank, 'campus' => $lecturer->campus?->name, 'avatar_url' => $lecturer->avatar_url], 'token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expiresAt->toISOString(), 'impersonation_info' => ['impersonated_by' => ['id' => $administrator->id, 'name' => $administrator->name, 'email' => $administrator->email], 'impersonated_at' => now()->toISOString(), 'purpose' => $administrator->purpose ?? 'Admin support']]);
    }
}
