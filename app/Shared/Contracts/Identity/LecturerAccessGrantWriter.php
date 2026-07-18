<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;
use App\Shared\Contracts\Identity\DTO\LecturerAccessGrant;

interface LecturerAccessGrantWriter
{
    public function apply(FacultyAccessEligibility $eligibility): LecturerAccessGrant;
}
