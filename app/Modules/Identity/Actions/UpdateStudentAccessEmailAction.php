<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;

final class UpdateStudentAccessEmailAction
{
    /** @param array{account_id: int, email: string} $data */
    public static function run(array $data): void
    {
        User::query()->findOrFail($data['account_id'])->update([
            'email' => $data['email'],
        ]);
    }
}
