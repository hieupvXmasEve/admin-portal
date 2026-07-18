<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\CampusUserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RevokeStudentAccessAction
{
    /** @param array{account_id: int} $data */
    public static function run(array $data): void
    {
        DB::transaction(function () use ($data): void {
            CampusUserRole::query()->where('user_id', $data['account_id'])->delete();
            User::query()->find($data['account_id'])?->delete();
        });
    }
}
