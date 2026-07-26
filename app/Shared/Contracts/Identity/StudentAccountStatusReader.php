<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface StudentAccountStatusReader
{
    public function hasActiveAccountForStudent(int $studentId): bool;
}
