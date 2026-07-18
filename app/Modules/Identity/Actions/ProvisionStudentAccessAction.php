<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\User;
use App\Shared\Contracts\Identity\DTO\StudentAccessAccount;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ProvisionStudentAccessAction
{
    /**
     * @param  array{campus_id: int, full_name: string, email: string}  $data
     */
    public static function run(array $data): StudentAccessAccount
    {
        return DB::transaction(function () use ($data): StudentAccessAccount {
            $account = User::query()->create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::password(40)),
                'type' => UserType::STUDENT,
                'status' => User::STATUS_ACTIVE,
            ]);

            $studentRole = Role::query()->where('code', 'sinh_vien')->first();

            if ($studentRole !== null) {
                CampusUserRole::query()->create([
                    'user_id' => $account->id,
                    'role_id' => $studentRole->id,
                    'campus_id' => $data['campus_id'],
                    'assigned_at' => now(),
                ]);
            }

            return new StudentAccessAccount(
                id: (int) $account->id,
                email: (string) $account->email,
            );
        });
    }
}
