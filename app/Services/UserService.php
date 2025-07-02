<?php

namespace App\Services;

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
        $userData['password'] = Hash::make($userData['password']);

        Log::info('Creating a new user', ['email' => $userData['email']]);
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

        // Remove existing roles for this campus
        $user->campusRoles()->where('campus_id', $currentCampusId)->delete();

        // Add new roles for this campus
        if (!empty($roleIds)) {
            $rolesToSync = [];
            foreach ($roleIds as $roleId) {
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
            $user->campusRoles()->where('campus_id', $currentCampusId)->delete();
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
