<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\AdmittedStudentIdentity;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;

interface StudentIdentityWriter
{
    public function register(AdmittedStudentIdentity $identity): StudentReference;

    public function requireRevocable(int $studentId): StudentReference;

    public function revoke(int $studentId): void;
}
