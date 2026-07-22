<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UpdateUserAction
{
    public static function run(User $user, array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();
        Log::info('Updating user', ['userData' => $userData]);

        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }

        return DB::transaction(function () use ($data, $user, $userData): User {
            Log::info("Updating user {$user->id}", ['email' => $user->email]);
            $user->update($userData);

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
