<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Exceptions\BusinessLogicException;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubMemberRoleHistory;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ClubMembershipOperations
{
    public function __construct(private readonly StudentReferenceReader $studentReferenceReader) {}

    /**
     * Apply for membership in a club.
     *
     * @throws BusinessLogicException
     */
    public function applyForMembership(Club $club, int $studentId, ?string $notes = null): ClubMember
    {
        return DB::transaction(function () use ($club, $studentId, $notes) {
            // Validate that the club is active
            if (! $club->isActive()) {
                throw new BusinessLogicException(
                    'Cannot apply to an inactive club.',
                    ['club' => ['Club is not currently accepting applications']]
                );
            }

            // Validate that the student exists and is on the same campus
            $student = $this->studentReferenceReader->find($studentId);
            if (! $student) {
                throw new BusinessLogicException(
                    'Student not found.',
                    ['student' => ['Student does not exist']]
                );
            }

            if ($student->campusId !== $club->campus_id) {
                throw new BusinessLogicException(
                    'Student must be from the same campus as the club.',
                    ['student' => ['Student must belong to the same campus as the club']]
                );
            }

            // Check if student already has a membership or pending application
            $existingMembership = ClubMember::where('club_id', $club->id)
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'pending'])
                ->first();

            if ($existingMembership) {
                $status = $existingMembership->status;
                throw new BusinessLogicException(
                    $status === 'active'
                        ? 'Student is already a member of this club.'
                        : 'Student already has a pending application for this club.',
                    ['membership' => ["Student already has a {$status} membership"]]
                );
            }

            // Create membership application
            $membership = ClubMember::create([
                'club_id' => $club->id,
                'student_id' => $studentId,
                'role' => 'member',
                'status' => 'pending',
                'application_notes' => $notes,
            ]);

            Log::info('Membership application created', [
                'club_id' => $club->id,
                'club_name' => $club->name,
                'student_id' => $studentId,
                'student_name' => $student->fullName,
                'application_notes' => $notes,
            ]);

            return $membership->fresh(['student', 'club']);
        });
    }

    /**
     * Approve a membership application.
     *
     * @throws BusinessLogicException
     */
    public function approveMembership(ClubMember $member, int $approvedBy): ClubMember
    {
        return DB::transaction(function () use ($member, $approvedBy) {
            // Validate that the membership is pending
            if (! $member->isPending()) {
                throw new BusinessLogicException(
                    'Only pending applications can be approved.',
                    ['membership' => ['Membership is not in pending status']]
                );
            }

            // Validate that the approver is the club president
            $president = $member->club->president;
            if (! $president || $president->student_id !== $approvedBy) {
                throw new BusinessLogicException(
                    'Only the club president can approve memberships.',
                    ['authorization' => ['You must be the club president to approve memberships']]
                );
            }

            // Update membership status
            $member->update([
                'status' => 'active',
                'approved_by' => $approvedBy,
                'joined_at' => now(),
            ]);

            // Create role history for the approval
            ClubMemberRoleHistory::create([
                'club_member_id' => $member->id,
                'old_role' => null,
                'new_role' => $member->role,
                'changed_by' => $approvedBy,
                'change_reason' => 'Membership approved',
                'started_at' => now(),
            ]);

            Log::info('Membership approved', [
                'club_id' => $member->club_id,
                'club_name' => $member->club->name,
                'student_id' => $member->student_id,
                'student_name' => $member->student->full_name,
                'approved_by' => $approvedBy,
            ]);

            return $member->fresh(['student', 'club', 'approver']);
        });
    }

    /**
     * Reject a membership application.
     *
     * @throws BusinessLogicException
     */
    public function rejectMembership(ClubMember $member, int $rejectedBy, ?string $reason = null): ClubMember
    {
        return DB::transaction(function () use ($member, $rejectedBy, $reason) {
            // Validate that the membership is pending
            if (! $member->isPending()) {
                throw new BusinessLogicException(
                    'Only pending applications can be rejected.',
                    ['membership' => ['Membership is not in pending status']]
                );
            }

            // Validate that the rejector is the club president
            $president = $member->club->president;
            if (! $president || $president->student_id !== $rejectedBy) {
                throw new BusinessLogicException(
                    'Only the club president can reject memberships.',
                    ['authorization' => ['You must be the club president to reject memberships']]
                );
            }

            // Update membership status
            $member->update([
                'status' => 'rejected',
                'approved_by' => $rejectedBy, // Store who made the decision
                'application_notes' => $member->application_notes.
                    ($reason ? "\n\nRejection reason: ".$reason : ''),
            ]);

            Log::info('Membership rejected', [
                'club_id' => $member->club_id,
                'club_name' => $member->club->name,
                'student_id' => $member->student_id,
                'student_name' => $member->student->full_name,
                'rejected_by' => $rejectedBy,
                'reason' => $reason,
            ]);

            return $member->fresh(['student', 'club', 'approver']);
        });
    }

    /**
     * Update a member's role with history tracking.
     *
     * @throws BusinessLogicException
     */
    public function updateMemberRole(ClubMember $member, string $newRole, int $changedBy, ?string $reason = null): ClubMember
    {
        return DB::transaction(function () use ($member, $newRole, $changedBy, $reason) {
            // Validate that the member is active
            if (! $member->isActive()) {
                throw new BusinessLogicException(
                    'Only active members can have their roles updated.',
                    ['membership' => ['Member is not in active status']]
                );
            }

            // Validate that the changer is the club president
            $president = $member->club->president;
            if (! $president || $president->student_id !== $changedBy) {
                throw new BusinessLogicException(
                    'Only the club president can update member roles.',
                    ['authorization' => ['You must be the club president to update member roles']]
                );
            }

            // Validate the new role
            $validRoles = ['president', 'vice_president', 'secretary', 'treasurer', 'member'];
            if (! in_array($newRole, $validRoles)) {
                throw new BusinessLogicException(
                    'Invalid role specified.',
                    ['role' => ['Role must be one of: '.implode(', ', $validRoles)]]
                );
            }

            // Prevent assigning president role (use assignPresident method instead)
            if ($newRole === 'president') {
                throw new BusinessLogicException(
                    'Cannot assign president role through this method. Use assignPresident instead.',
                    ['role' => ['President role must be assigned through the proper method']]
                );
            }

            // Check if role is actually changing
            if ($member->role === $newRole) {
                throw new BusinessLogicException(
                    'Member already has this role.',
                    ['role' => ['Member is already assigned to this role']]
                );
            }

            $oldRole = $member->role;

            // End current role history
            ClubMemberRoleHistory::where('club_member_id', $member->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Update member role
            $member->update(['role' => $newRole]);

            // Create new role history
            ClubMemberRoleHistory::create([
                'club_member_id' => $member->id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'changed_by' => $changedBy,
                'change_reason' => $reason ?? 'Role updated',
                'started_at' => now(),
            ]);

            Log::info('Member role updated', [
                'club_id' => $member->club_id,
                'club_name' => $member->club->name,
                'student_id' => $member->student_id,
                'student_name' => $member->student->full_name,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);

            return $member->fresh(['student', 'club', 'roleHistory']);
        });
    }

    /**
     * Remove a member from the club.
     *
     * @throws BusinessLogicException
     */
    public function removeMember(ClubMember $member, int $removedBy, ?string $reason = null): ClubMember
    {
        return DB::transaction(function () use ($member, $removedBy, $reason) {
            // Validate that the member is active
            if (! $member->isActive()) {
                throw new BusinessLogicException(
                    'Only active members can be removed.',
                    ['membership' => ['Member is not in active status']]
                );
            }

            // Validate that the remover is the club president
            $president = $member->club->president;
            if (! $president || $president->student_id !== $removedBy) {
                throw new BusinessLogicException(
                    'Only the club president can remove members.',
                    ['authorization' => ['You must be the club president to remove members']]
                );
            }

            // Prevent president from removing themselves
            if ($member->isPresident() && $member->student_id === $removedBy) {
                throw new BusinessLogicException(
                    'President cannot remove themselves. Transfer presidency first.',
                    ['membership' => ['President must transfer role before leaving the club']]
                );
            }

            // End current role history
            ClubMemberRoleHistory::where('club_member_id', $member->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Update member status
            $member->update([
                'status' => 'left',
                'left_at' => now(),
                'application_notes' => $member->application_notes.
                    ($reason ? "\n\nRemoval reason: ".$reason : ''),
            ]);

            Log::info('Member removed from club', [
                'club_id' => $member->club_id,
                'club_name' => $member->club->name,
                'student_id' => $member->student_id,
                'student_name' => $member->student->full_name,
                'removed_by' => $removedBy,
                'reason' => $reason,
            ]);

            return $member->fresh(['student', 'club']);
        });
    }

    /**
     * Allow a member to leave the club voluntarily.
     *
     * @throws BusinessLogicException
     */
    public function leaveClub(ClubMember $member, ?string $reason = null): ClubMember
    {
        return DB::transaction(function () use ($member, $reason) {
            // Validate that the member is active
            if (! $member->isActive()) {
                throw new BusinessLogicException(
                    'Only active members can leave the club.',
                    ['membership' => ['Member is not in active status']]
                );
            }

            // Prevent president from leaving without transferring role
            if ($member->isPresident()) {
                throw new BusinessLogicException(
                    'President cannot leave the club. Transfer presidency first.',
                    ['membership' => ['President must transfer role before leaving the club']]
                );
            }

            // End current role history
            ClubMemberRoleHistory::where('club_member_id', $member->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Update member status
            $member->update([
                'status' => 'left',
                'left_at' => now(),
                'application_notes' => $member->application_notes.
                    ($reason ? "\n\nLeft voluntarily: ".$reason : "\n\nLeft voluntarily"),
            ]);

            Log::info('Member left club voluntarily', [
                'club_id' => $member->club_id,
                'club_name' => $member->club->name,
                'student_id' => $member->student_id,
                'student_name' => $member->student->full_name,
                'reason' => $reason,
            ]);

            return $member->fresh(['student', 'club']);
        });
    }
}
