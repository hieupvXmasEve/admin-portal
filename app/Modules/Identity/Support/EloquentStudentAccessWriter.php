<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Actions\ProvisionStudentAccessAction;
use App\Modules\Identity\Actions\RevokeStudentAccessAction;
use App\Modules\Identity\Actions\UpdateStudentAccessEmailAction;
use App\Shared\Contracts\Identity\DTO\StudentAccessAccount;
use App\Shared\Contracts\Identity\StudentAccessWriter;

final class EloquentStudentAccessWriter implements StudentAccessWriter
{
    public function provision(int $campusId, string $fullName, string $email): StudentAccessAccount
    {
        return ProvisionStudentAccessAction::run([
            'campus_id' => $campusId,
            'full_name' => $fullName,
            'email' => $email,
        ]);
    }

    public function updateEmail(int $accountId, string $email): void
    {
        UpdateStudentAccessEmailAction::run([
            'account_id' => $accountId,
            'email' => $email,
        ]);
    }

    public function revoke(int $accountId): void
    {
        RevokeStudentAccessAction::run(['account_id' => $accountId]);
    }
}
