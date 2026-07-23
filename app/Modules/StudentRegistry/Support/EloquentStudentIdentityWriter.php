<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Modules\StudentRegistry\Actions\RegisterAdmittedStudentAction;
use App\Modules\StudentRegistry\Actions\RequireRevocableStudentIdentityAction;
use App\Modules\StudentRegistry\Actions\RevokeStudentIdentityAction;
use App\Modules\StudentRegistry\Actions\UpdateStudentIdentityEmailAction;
use App\Shared\Contracts\StudentRegistry\DTO\AdmittedStudentIdentity;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;

final class EloquentStudentIdentityWriter implements StudentIdentityWriter
{
    public function register(AdmittedStudentIdentity $identity): StudentReference
    {
        return RegisterAdmittedStudentAction::run(['identity' => $identity]);
    }

    public function updateEmail(int $studentId, string $email): void
    {
        UpdateStudentIdentityEmailAction::run([
            'student_id' => $studentId,
            'email' => $email,
        ]);
    }

    public function requireRevocable(int $studentId): StudentReference
    {
        return RequireRevocableStudentIdentityAction::run(['student_id' => $studentId]);
    }

    public function revoke(int $studentId): void
    {
        RevokeStudentIdentityAction::run(['student_id' => $studentId]);
    }
}
