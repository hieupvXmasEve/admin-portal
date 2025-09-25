<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClubMember;
use App\Models\User;

class ClubMemberPolicy
{
    /**
     * Determine whether the user can view any club members.
     */
    public function viewAny(User $user): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.view_clubs'));
    }

    /**
     * Determine whether the user can view the club member.
     */
    public function view(User $user, ClubMember $member): bool
    {
        // Admin users with view permission can see all club members
        if (session('permissions')->contains(config('permission.access.clubs.view_clubs'))) {
            return true;
        }

        // Club president can view all members of their club
        if ($this->isPresidentOfClub($user, $member->club)) {
            return true;
        }

        // Users can view their own membership
        if ($user->student && $member->student_id === $user->student->id) {
            return true;
        }

        // Active club members can view other active members of the same club
        if ($this->isActiveMemberOfClub($user, $member->club) && $member->isActive()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create club members (apply for membership).
     */
    public function create(User $user): bool
    {
        // Any authenticated user with a student profile can apply for membership
        return $user->student !== null;
    }

    /**
     * Determine whether the user can approve a membership application.
     */
    public function approve(User $user, ClubMember $member): bool
    {
        // Admin users with edit permission can approve any membership
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Only pending applications can be approved
        if (!$member->isPending()) {
            return false;
        }

        // Club president can approve memberships
        return $this->isPresidentOfClub($user, $member->club);
    }

    /**
     * Determine whether the user can reject a membership application.
     */
    public function reject(User $user, ClubMember $member): bool
    {
        // Admin users with edit permission can reject any membership
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Only pending applications can be rejected
        if (!$member->isPending()) {
            return false;
        }

        // Club president can reject memberships
        return $this->isPresidentOfClub($user, $member->club);
    }

    /**
     * Determine whether the user can update the club member's role.
     */
    public function updateRole(User $user, ClubMember $member): bool
    {
        // Admin users with edit permission can update any member's role
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Only active members can have their roles updated
        if (!$member->isActive()) {
            return false;
        }

        // Club president can update member roles (except their own president role)
        if ($this->isPresidentOfClub($user, $member->club)) {
            // Presidents cannot demote themselves
            if ($user->student && $member->student_id === $user->student->id && $member->isPresident()) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can remove a member from the club.
     */
    public function remove(User $user, ClubMember $member): bool
    {
        // Admin users with edit permission can remove any member
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Only active members can be removed
        if (!$member->isActive()) {
            return false;
        }

        // Club president can remove members (except themselves)
        if ($this->isPresidentOfClub($user, $member->club)) {
            // Presidents cannot remove themselves
            if ($user->student && $member->student_id === $user->student->id) {
                return false;
            }
            return true;
        }

        // Members can remove themselves (leave the club)
        if ($user->student && $member->student_id === $user->student->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the club member.
     */
    public function update(User $user, ClubMember $member): bool
    {
        // Admin users with edit permission can update any membership
        if (session('permissions')->contains(config('permission.access.clubs.edit_clubs'))) {
            return true;
        }

        // Club president can update member information
        if ($this->isPresidentOfClub($user, $member->club)) {
            return true;
        }

        // Users can update their own membership information (limited fields)
        if ($user->student && $member->student_id === $user->student->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the club member.
     */
    public function delete(User $user, ClubMember $member): bool
    {
        // Only admin users can permanently delete membership records
        return session('permissions')->contains(config('permission.access.clubs.delete_clubs'));
    }

    /**
     * Determine whether the user can restore the club member.
     */
    public function restore(User $user, ClubMember $member): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.edit_clubs'));
    }

    /**
     * Determine whether the user can permanently delete the club member.
     */
    public function forceDelete(User $user, ClubMember $member): bool
    {
        return session('permissions')->contains(config('permission.access.clubs.delete_clubs'));
    }

    /**
     * Check if the user is the president of the specified club.
     */
    private function isPresidentOfClub(User $user, $club): bool
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

    /**
     * Check if the user is an active member of the specified club.
     */
    private function isActiveMemberOfClub(User $user, $club): bool
    {
        if (!$user->student) {
            return false;
        }

        return $club->members()
            ->where('student_id', $user->student->id)
            ->where('status', 'active')
            ->exists();
    }
}
