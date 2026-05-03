<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    protected RoleAssignmentService $roleAssignmentService;

    public function __construct(RoleAssignmentService $roleAssignmentService)
    {
        $this->roleAssignmentService = $roleAssignmentService;
    }
    /** @deprecated Logic moved to App\Modules\Identity\Actions\CreateUserAction. Remove after 2024-12-31. */
    public function createUser(array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();
        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        Log::info('Creating a new user', ['$userData' => $userData]);
        $user = User::create($userData);

        if (array_key_exists('selectedRoles', $data)) {
            $this->syncCampusRoles($user, $data['selectedRoles']);
        }

        return $user;
    }

    /** @deprecated Logic moved to App\Modules\Identity\Actions\UpdateUserAction. Remove after 2024-12-31. */
    public function updateUser(User $user, array $data): User
    {
        $userData = collect($data)->except('selectedRoles')->toArray();
        Log::info('Updating user', ['userData' => $userData]);
        if (! empty($userData['password'])) {
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
     * Sync roles for a user on the current campus using RoleAssignmentService.
     */
    public function syncCampusRoles(User $user, array $roleIds): void
    {
        $currentCampusId = session('current_campus_id');
        if (! $currentCampusId) {
            return;
        }

        // Validate that all role IDs exist in the roles table
        $validRoleIds = [];
        if (! empty($roleIds)) {
            $validRoleIds = Role::whereIn('id', $roleIds)->pluck('id')->toArray();

            if (empty($validRoleIds)) {
                Log::warning("No valid role IDs found for user {$user->id}", ['provided_roles' => $roleIds]);
                $validRoleIds = [];
            }
        }

        // ✅ Sử dụng RoleAssignmentService để sync roles và auto clear cache
        $this->roleAssignmentService->assignMultipleRolesToUser($user, $validRoleIds, $currentCampusId);
        
        Log::info("Synced roles for user {$user->id} on campus {$currentCampusId}");
    }

    /** @deprecated Logic moved to App\Modules\Identity\Actions\DeleteUserAction. Remove after 2024-12-31. */
    public function deleteUser(User $user): void
    {
        $currentCampusId = session('current_campus_id');

        if ($currentCampusId) {
            // ✅ Sử dụng RoleAssignmentService để xóa tất cả roles và auto clear cache
            $this->roleAssignmentService->assignMultipleRolesToUser($user, [], $currentCampusId);
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
