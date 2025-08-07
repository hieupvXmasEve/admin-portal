<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    /**
     * Create a new user.
     *
     * @param array $data Validated data.
     * @return User
     */
    public function createUser(array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();
        if (!empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        Log::info('Creating a new user', ['$userData' => $userData]);
        $user = User::create($userData);

        if (array_key_exists('selectedRoles', $data)) {
            $this->syncCampusRoles($user, $data['selectedRoles']);
        }

        return $user;
    }

    /**
     * Update an existing user.
     *
     * @param User $user The user to update.
     * @param array $data Validated data.
     * @return User
     */
    public function updateUser(User $user, array $data): User
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
            $this->syncCampusRoles($user, $data['selectedRoles']);
        }

        return $user;
    }

    /**
     * Sync roles for a user on the current campus.
     *
     * @param User $user
     * @param array $roleIds
     * @return void
     */
    public function syncCampusRoles(User $user, array $roleIds): void
    {
        $currentCampusId = session('current_campus_id');
        if (!$currentCampusId) {
            return;
        }

        // Get current roles for this campus
        $existingRoleIds = $user->campusRoles()
            ->where('campus_id', $currentCampusId)
            ->pluck('role_id')
            ->toArray();

        // Validate that all role IDs exist in the roles table
        $validRoleIds = [];
        if (!empty($roleIds)) {
            $validRoleIds = Role::whereIn('id', $roleIds)->pluck('id')->toArray();

            if (empty($validRoleIds)) {
                Log::warning("No valid role IDs found for user {$user->id}", ['provided_roles' => $roleIds]);
                // If no valid roles found, treat as empty array (remove all roles)
                $validRoleIds = [];
            }
        }

        // Find roles to remove and roles to add
        $rolesToRemove = array_diff($existingRoleIds, $validRoleIds);
        $rolesToAdd = array_diff($validRoleIds, $existingRoleIds);

        // Remove roles that are no longer selected
        if (!empty($rolesToRemove)) {
            \App\Models\CampusUserRole::where('user_id', $user->id)
                ->where('campus_id', $currentCampusId)
                ->whereIn('role_id', $rolesToRemove)
                ->delete();
        }

        // Add new roles
        if (!empty($rolesToAdd)) {
            $rolesToSync = [];
            foreach ($rolesToAdd as $roleId) {
                $rolesToSync[] = [
                    'user_id' => $user->id,
                    'campus_id' => $currentCampusId,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            \App\Models\CampusUserRole::insert($rolesToSync);
        }
        Log::info("Synced roles for user {$user->id} on campus {$currentCampusId}");
    }

    /**
     * Delete a user.
     *
     * @param User $user The user to delete.
     * @return void
     */
    public function deleteUser(User $user): void
    {
        $currentCampusId = session('current_campus_id');

        if ($currentCampusId) {
            // Remove user's roles for this campus only
            \App\Models\CampusUserRole::where('user_id', $user->id)
                ->where('campus_id', $currentCampusId)
                ->delete();
        }

        // If user has no roles in any campus, delete the user
        if ($user->campusRoles()->count() === 0) {
            Log::warning("Deleting user {$user->id} as they have no remaining campus roles.");
            $user->delete();
        } else {
            Log::info("Removed user {$user->id} from campus {$currentCampusId}.");
        }
    }
}
