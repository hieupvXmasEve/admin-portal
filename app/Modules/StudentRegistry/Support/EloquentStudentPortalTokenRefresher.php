<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\Identity\StudentAccountStatusReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentPortalToken;
use App\Shared\Contracts\StudentRegistry\StudentPortalTokenRefresher;

final class EloquentStudentPortalTokenRefresher implements StudentPortalTokenRefresher
{
    public function __construct(private readonly StudentAccountStatusReader $accounts) {}

    public function refresh(int $studentId, ?string $deviceName = null): StudentPortalToken
    {
        $student = Student::query()->findOrFail($studentId);
        if (! $this->accounts->hasActiveAccountForStudent($studentId) || ! $student->isActive()) {
            throw new \DomainException('Account is not active. Please contact administration.');
        }

        $expiresAt = now()->addHours(8);

        return new StudentPortalToken(
            token: $student->createToken($deviceName ?? 'Student Portal', ['student'], $expiresAt)->plainTextToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->toISOString(),
        );
    }
}
