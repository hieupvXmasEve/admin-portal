<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    /**
     * Determine whether the user can view any clubs.
     */
    public function viewAny(User $user): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.view_clubs'));
    }

    /**
     * Determine whether the user can view the club.
     */
    public function view(User $user, Club $club): bool
    {
        // Admin users with view permission can see all clubs
        if (session('permissions')->contains(config('permission.access.clubs.view_clubs'))) {
            return true;
        }

        // Students can view active clubs on their campus
        if ($user->student && $club->isActive() && $club->campus_id === $user->student->campus_id) {
            return true;
        }

        // Club members can view their club regardless of status
        return $this->isMemberOfClub($user, $club);
    }

    /**
     * Determine whether the user can create clubs.
     */
    public function create(User $user): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.create_clubs'));
    }

    /**
     * Determine whether the user can update the club.
     */
    public function update(User $user, Club $club): bool
    {
        // Admin users with edit permission can update any club
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Club president can update their club
        return $this->isPresidentOfClub($user, $club);
    }

    /**
     * Determine whether the user can delete the club.
     */
    public function delete(User $user, Club $club): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.delete_clubs'));
    }

    /**
     * Determine whether the user can manage members of the club.
     */
    public function manageMembers(User $user, Club $club): bool
    {
        // Admin users with edit permission can manage members of any club
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Club president can manage members
        return $this->isPresidentOfClub($user, $club);
    }

    /**
     * Determine whether the user can assign roles in the club.
     */
    public function assignRoles(User $user, Club $club): bool
    {
        // Admin users with edit permission can assign roles in any club
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Club president can assign roles
        return $this->isPresidentOfClub($user, $club);
    }

    /**
     * Determine whether the user can assign a president to the club.
     */
    public function assignPresident(User $user, Club $club): bool
    {
        // Only admin users can assign presidents
        return session('permissions')->contains(config('permission.access.clubs.edit_clubs'));
    }

    /**
     * Determine whether the user can restore the club.
     */
    public function restore(User $user, Club $club): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.edit_clubs'));
    }

    /**
     * Determine whether the user can permanently delete the club.
     */
    public function forceDelete(User $user, Club $club): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.delete_clubs'));
    }

    /**
     * Check if the user is a member of the club.
     */
    private function isMemberOfClub(User $user, Club $club): bool
    {
        if (!$user->student) {
            return false;
        }

        return $club->members()
            ->where('student_id', $user->student->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if the user is the president of the club.
     */
    private function isPresidentOfClub(User $user, Club $club): bool
    {
        if (!$user->student) {
            return false;
        }

        return $club->members()
            ->where('student_id', $user->student->id)
            ->where('role', 'president')
            ->where('status', 'active')
            ->exists();
    }
}
