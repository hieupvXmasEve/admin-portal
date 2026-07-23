<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\StudentProfile;

interface StudentProfileReader
{
    public function findProfile(int $studentId): ?StudentProfile;
}
