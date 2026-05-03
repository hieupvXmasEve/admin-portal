<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeleteUserAction
{
    public static function run(User $user): void
    {
        $currentCampusId = session('current_campus_id');

        if ($currentCampusId) {
            // Remove user roles from current campus
            $user->campusRoles()->wherePivot('campus_id', $currentCampusId)->detach();
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