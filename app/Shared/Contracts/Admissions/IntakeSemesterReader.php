<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Admissions;

interface IntakeSemesterReader
{
    /** Current CRM-mapped intake semester id, or null if unconfigured. */
    public function currentIntakeSemesterId(): ?int;
}
