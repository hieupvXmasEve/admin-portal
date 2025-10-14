<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubMemberRoleHistory;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClubService
{
    /**
     * Create a new club with president assignment.
     *
     * @param  array  $data  Club data
     * @param  int  $presidentStudentId  Student ID to assign as president
     *
     * @throws BusinessLogicException
     */
    public function createClub(array $data, int $presidentStudentId): Club
    {
        return DB::transaction(function () use ($data, $presidentStudentId) {
            // Validate that club name doesn't exist on the same campus
            $existingClub = Club::where('campus_id', $data['campus_id'])
                ->where('name', $data['name'])
                ->first();

            if ($existingClub) {
                throw new BusinessLogicException(
                    'A club with this name already exists on this campus.',
                    ['name' => ['Club name must be unique per campus']]
                );
            }

            // Validate that the student exists and is on the same campus
            $student = Student::with('campus')->find($presidentStudentId);
            if (! $student) {
                throw new BusinessLogicException(
                    'Student not found.',
                    ['president' => ['Selected student does not exist']]
                );
            }

            if ($student->campus_id !== $data['campus_id']) {
                throw new BusinessLogicException(
                    'Student must be from the same campus as the club.',
                    ['president' => ['Student must belong to the same campus']]
                );
            }

            // Check if student is already a president of another club
            $existingPresidency = ClubMember::where('student_id', $presidentStudentId)
                ->where('role', 'president')
                ->where('status', 'active')
                ->first();

            if ($existingPresidency) {
                throw new BusinessLogicException(
                    'Student is already a president of another club.',
                    ['president' => ['Student can only be president of one club at a time']]
                );
            }

            // Create the club
            $club = Club::create($data);

            Log::info('Club created', [
                'club_id' => $club->id,
                'club_name' => $club->name,
                'campus_id' => $club->campus_id,
                'president_student_id' => $presidentStudentId,
            ]);

            // Assign president
            $this->assignPresident($club, $presidentStudentId, 'Initial club creation');

            return $club->fresh(['president', 'members']);
        });
    }

    /**
     * Update club information.
     *
     * @throws BusinessLogicException
     */
    public function updateClub(Club $club, array $data): Club
    {
        return DB::transaction(function () use ($club, $data) {
            // If name is being changed, validate uniqueness
            if (isset($data['name']) && $data['name'] !== $club->name) {
                $existingClub = Club::where('campus_id', $club->campus_id)
                    ->where('name', $data['name'])
                    ->where('id', '!=', $club->id)
                    ->first();

                if ($existingClub) {
                    throw new BusinessLogicException(
                        'A club with this name already exists on this campus.',
                        ['name' => ['Club name must be unique per campus']]
                    );
                }
            }

            // Update the club
            $club->update($data);

            Log::info('Club updated', [
                'club_id' => $club->id,
                'club_name' => $club->name,
                'updated_fields' => array_keys($data),
            ]);

            return $club->fresh();
        });
    }

    /**
     * Assign a new president to a club.
     *
     * @throws BusinessLogicException
     */
    public function assignPresident(Club $club, int $studentId, ?string $reason = null): ClubMember
    {
        return DB::transaction(function () use ($club, $studentId, $reason) {
            // Validate that the student exists and is on the same campus
            $student = Student::find($studentId);
            if (! $student) {
                throw new BusinessLogicException(
                    'Student not found.',
                    ['student_id' => ['Selected student does not exist']]
                );
            }

            if ($student->campus_id !== $club->campus_id) {
                throw new BusinessLogicException(
                    'Student must be from the same campus as the club.',
                    ['student_id' => ['Student must belong to the same campus']]
                );
            }

            // Check if student is already a president of another club
            $existingPresidency = ClubMember::where('student_id', $studentId)
                ->where('role', 'president')
                ->where('status', 'active')
                ->whereHas('club', function ($query) use ($club) {
                    $query->where('id', '!=', $club->id);
                })
                ->first();

            if ($existingPresidency) {
                throw new BusinessLogicException(
                    'Student is already a president of another club.',
                    ['student_id' => ['Student can only be president of one club at a time']]
                );
            }

            // End current president's role if exists
            $currentPresident = $club->president;
            if ($currentPresident) {
                // End the current president's role history
                ClubMemberRoleHistory::where('club_member_id', $currentPresident->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);

                // Update current president to member role
                $currentPresident->update(['role' => 'member']);

                // Create role history for the demotion
                ClubMemberRoleHistory::create([
                    'club_member_id' => $currentPresident->id,
                    'old_role' => 'president',
                    'new_role' => 'member',
                    'changed_by' => null, // Admin/staff action, not student
                    'change_reason' => 'New president assigned',
                    'started_at' => now(),
                ]);
            }

            // Check if the student is already a member
            $existingMember = ClubMember::where('club_id', $club->id)
                ->where('student_id', $studentId)
                ->first();

            if ($existingMember) {
                // Update existing member to president
                $oldRole = $existingMember->role;
                $existingMember->update([
                    'role' => 'president',
                    'status' => 'active',
                    'joined_at' => $existingMember->joined_at ?? now(),
                ]);

                // End previous role history
                ClubMemberRoleHistory::where('club_member_id', $existingMember->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);

                // Create new role history
                ClubMemberRoleHistory::create([
                    'club_member_id' => $existingMember->id,
                    'old_role' => $oldRole,
                    'new_role' => 'president',
                    'changed_by' => null, // Admin/staff action, not student
                    'change_reason' => $reason ?? 'Assigned as president',
                    'started_at' => now(),
                ]);

                $newPresident = $existingMember;
            } else {
                // Create new membership as president
                $newPresident = ClubMember::create([
                    'club_id' => $club->id,
                    'student_id' => $studentId,
                    'role' => 'president',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                // Create role history
                ClubMemberRoleHistory::create([
                    'club_member_id' => $newPresident->id,
                    'old_role' => null,
                    'new_role' => 'president',
                    'changed_by' => null, // Admin/staff action, not student
                    'change_reason' => $reason ?? 'Initial president assignment',
                    'started_at' => now(),
                ]);
            }

            Log::info('President assigned to club', [
                'club_id' => $club->id,
                'club_name' => $club->name,
                'new_president_id' => $studentId,
                'previous_president_id' => $currentPresident?->student_id,
                'reason' => $reason,
            ]);

            return $newPresident->fresh(['student', 'club']);
        });
    }

    /**
     * Get clubs for a specific student.
     */
    public function getClubsForStudent(int $studentId): Collection
    {
        $student = Student::find($studentId);
        if (! $student) {
            return collect();
        }

        // Get clubs where the student is a member
        $memberClubs = Club::whereHas('members', function ($query) use ($studentId) {
            $query->where('student_id', $studentId)
                ->whereIn('status', ['active', 'pending']);
        })->with([
            'campus',
            'president.student',
            'members' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            },
        ])->get();

        // Get all active clubs on the same campus for discovery
        $availableClubs = Club::active()
            ->where('campus_id', $student->campus_id)
            ->whereDoesntHave('members', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->whereIn('status', ['active', 'pending']);
            })
            ->with(['campus', 'president.student'])
            ->get();

        return $memberClubs->merge($availableClubs);
    }

    /**
     * Add a member to the club.
     *
     * @throws BusinessLogicException
     */
    public function addMember(Club $club, int $studentId, string $role = 'member', ?string $notes = null): ClubMember
    {
        return DB::transaction(function () use ($club, $studentId, $role, $notes) {
            // Validate that the student exists and is on the same campus
            $student = Student::find($studentId);
            if (! $student) {
                throw new BusinessLogicException(
                    'Student not found.',
                    ['student_id' => ['Selected student does not exist']]
                );
            }

            if ($student->campus_id !== $club->campus_id) {
                throw new BusinessLogicException(
                    'Student must be from the same campus as the club.',
                    ['student_id' => ['Student must belong to the same campus']]
                );
            }

            // Check if student is already a member
            $existingMember = ClubMember::where('club_id', $club->id)
                ->where('student_id', $studentId)
                ->first();

            if ($existingMember) {
                if ($existingMember->status === 'active') {
                    throw new BusinessLogicException(
                        'Student is already an active member of this club.',
                        ['student_id' => ['Student is already a member']]
                    );
                }

                // Reactivate if previously left or rejected
                if (in_array($existingMember->status, ['left', 'rejected'])) {
                    $oldRole = $existingMember->role;

                    $existingMember->update([
                        'status' => 'active',
                        'role' => $role,
                        'joined_at' => now(),
                    ]);

                    // Create role history for reactivation
                    ClubMemberRoleHistory::create([
                        'club_member_id' => $existingMember->id,
                        'old_role' => $oldRole,
                        'new_role' => $role,
                        'changed_by' => null, // Admin/staff action, not student
                        'change_reason' => $notes ?? 'Member reactivated',
                        'started_at' => now(),
                    ]);

                    Log::info('Club member reactivated', [
                        'club_id' => $club->id,
                        'student_id' => $studentId,
                        'role' => $role,
                    ]);

                    return $existingMember->fresh(['student', 'club']);
                }
            }

            // Create new membership
            $member = ClubMember::create([
                'club_id' => $club->id,
                'student_id' => $studentId,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Create role history
            ClubMemberRoleHistory::create([
                'club_member_id' => $member->id,
                'old_role' => null,
                'new_role' => $role,
                'changed_by' => null, // Admin/staff action, not student
                'change_reason' => $notes ?? 'Added as club member',
                'started_at' => now(),
            ]);

            Log::info('Club member added', [
                'club_id' => $club->id,
                'student_id' => $studentId,
                'role' => $role,
            ]);

            return $member->fresh(['student', 'club']);
        });
    }

    /**
     * Get club management data for a president.
     *
     * @throws BusinessLogicException
     */
    public function getClubManagementData(Club $club, int $studentId): array
    {
        // Verify the student is the president
        $president = $club->president;
        if (! $president || $president->student_id !== $studentId) {
            throw new BusinessLogicException(
                'Access denied. Only the club president can access management data.',
                ['authorization' => ['You must be the club president to access this data']]
            );
        }

        return [
            'club' => $club->load(['campus', 'president.student']),
            'members' => $club->members()
                ->with(['student', 'approver'])
                ->orderBy('status')
                ->orderBy('role')
                ->orderBy('joined_at')
                ->get(),
            'pending_applications' => $club->pendingApplications()
                ->with(['student'])
                ->orderBy('created_at')
                ->get(),
            'statistics' => [
                'total_members' => $club->activeMembers()->count(),
                'pending_applications' => $club->pendingApplications()->count(),
                'officers' => $club->officers()->count(),
            ],
            'recent_activities' => ClubMemberRoleHistory::whereHas('clubMember', function ($query) use ($club) {
                $query->where('club_id', $club->id);
            })
                ->with(['clubMember.student', 'changedBy'])
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}
