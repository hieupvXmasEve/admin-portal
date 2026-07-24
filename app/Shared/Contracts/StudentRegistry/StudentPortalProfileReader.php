<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\StudentPortalProfile;

interface StudentPortalProfileReader
{
    public function forStudent(int $studentId): StudentPortalProfile;
}
