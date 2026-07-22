<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateUserAction
{
    public static function run(array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();

        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        return DB::transaction(function () use ($data, $userData): User {
            Log::info('Creating a new user', ['$userData' => $userData]);
            $user = User::create($userData);

            if (array_key_exists('selectedRoles', $data)) {
                $currentCampusId = session('current_campus_id');
                if ($currentCampusId !== null) {
                    SyncUserCampusRolesAction::run([
                        'user_id' => (int) $user->id,
                        'role_ids' => $data['selectedRoles'],
                        'campus_id' => (int) $currentCampusId,
                    ]);
                }
            }

            return $user;
        });
    }
}
