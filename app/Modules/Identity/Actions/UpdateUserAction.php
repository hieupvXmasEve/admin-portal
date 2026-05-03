<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UpdateUserAction
{
    public static function run(User $user, array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();
        Log::info('Updating user', ['userData' => $userData]);
        
        if (!empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }

        Log::info("Updating user {$user->id}", ['email' => $user->email]);
        $user->update($userData);

        if (array_key_exists('selectedRoles', $data)) {
            self::syncCampusRoles($user, $data['selectedRoles']);
        }

        return $user;
    }

    /**
     * Sync roles for a user on the current campus.
     */
    private static function syncCampusRoles(User $user, array $roleIds): void
    {
        $currentCampusId = session('current_campus_id');
        if (!$currentCampusId) {
            return;
        }

        // Validate that all role IDs exist in the roles table
        $validRoleIds = [];
        if (!empty($roleIds)) {
            $validRoleIds = Role::whereIn('id', $roleIds)->pluck('id')->toArray();

            if (empty($validRoleIds)) {
                Log::warning("No valid role IDs found for user {$user->id}", ['provided_roles' => $roleIds]);
                $validRoleIds = [];
            }
        }

        // Sync roles for the current campus
        $user->campusRoles()->wherePivot('campus_id', $currentCampusId)->detach();
        
        foreach ($validRoleIds as $roleId) {
            $user->campusRoles()->attach($roleId, ['campus_id' => $currentCampusId]);
        }
        
        Log::info("Synced roles for user {$user->id} on campus {$currentCampusId}");
    }
}