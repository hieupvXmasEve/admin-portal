<?php

namespace Database\Factories\Modules\Engagement\Models;

use App\Models\Student;
use App\Modules\Engagement\Models\ClubMember;
use App\Modules\Engagement\Models\ClubMemberRoleHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMemberRoleHistory>
 */
class ClubMemberRoleHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roles = ['president', 'vice_president', 'secretary', 'treasurer', 'member'];

        $oldRole = fake()->randomElement([null, ...array_slice($roles, 0, -1)]); // null for initial assignment
        $newRole = fake()->randomElement($roles);

        // Ensure old and new roles are different if old role exists
        if ($oldRole !== null && $oldRole === $newRole) {
            $availableRoles = array_filter($roles, fn ($role) => $role !== $oldRole);
            $newRole = fake()->randomElement($availableRoles);
        }

        $startedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $endedAt = fake()->optional(0.3)->dateTimeBetween($startedAt, 'now'); // 30% chance of being ended

        return [
            'club_member_id' => ClubMember::factory(),
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'changed_by' => Student::factory(),
            'change_reason' => $this->generateChangeReason($oldRole, $newRole),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ];
    }

    /**
     * Generate a realistic change reason based on role transition.
     */
    private function generateChangeReason(?string $oldRole, string $newRole): string
    {
        if ($oldRole === null) {
            // Initial role assignment
            $initialReasons = [
                'Initial membership approval and role assignment',
                'New member onboarding with assigned responsibilities',
                'Club formation - founding member role assignment',
                'Membership application approved with designated role',
            ];

            return fake()->randomElement($initialReasons);
        }

        // Role changes
        $changeReasons = [
            'promotion' => [
                'Demonstrated exceptional leadership and commitment',
                'Elected by club members for outstanding service',
                'Promoted due to excellent performance and dedication',
                'Recognized for significant contributions to the club',
            ],
            'lateral' => [
                'Role reassignment to better match skills and interests',
                'Organizational restructuring for improved efficiency',
                'Member requested role change to explore new responsibilities',
                'Strategic role adjustment for club development',
            ],
            'demotion' => [
                'Unable to fulfill current role responsibilities',
                'Requested reduced responsibilities due to time constraints',
                'Performance issues requiring role adjustment',
                'Temporary role change due to academic commitments',
            ],
            'resignation' => [
                'Officer resigned from leadership position',
                'Stepped down to focus on academic priorities',
                'Personal circumstances required role change',
                'Completed term of service in leadership role',
            ],
        ];

        // Determine change type based on role hierarchy
        $roleHierarchy = ['member' => 1, 'treasurer' => 2, 'secretary' => 2, 'vice_president' => 3, 'president' => 4];
        $oldLevel = $roleHierarchy[$oldRole] ?? 1;
        $newLevel = $roleHierarchy[$newRole] ?? 1;

        if ($newLevel > $oldLevel) {
            $type = 'promotion';
        } elseif ($newLevel < $oldLevel) {
            $type = 'demotion';
        } else {
            $type = 'lateral';
        }

        return fake()->randomElement($changeReasons[$type]);
    }

    /**
     * Create an initial role assignment (no old role).
     */
    public function initialAssignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'old_role' => null,
            'change_reason' => fake()->randomElement([
                'Initial membership approval and role assignment',
                'New member onboarding with assigned responsibilities',
                'Club formation - founding member role assignment',
                'Membership application approved with designated role',
            ]),
        ]);
    }

    /**
     * Create an active role change (not ended).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    /**
     * Create an ended role change.
     */
    public function ended(): static
    {
        $startedAt = fake()->dateTimeBetween('-2 years', '-6 months');
        $endedAt = fake()->dateTimeBetween($startedAt, '-1 month');

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Create a promotion to president.
     */
    public function promotionToPresident(): static
    {
        $oldRoles = ['vice_president', 'secretary', 'treasurer', 'member'];

        return $this->state(fn (array $attributes) => [
            'old_role' => fake()->randomElement($oldRoles),
            'new_role' => 'president',
            'change_reason' => fake()->randomElement([
                'Elected as new club president by member vote',
                'Promoted to president due to exceptional leadership',
                'Appointed as president following previous president\'s graduation',
                'Selected as president for outstanding club contributions',
            ]),
        ]);
    }

    /**
     * Create a promotion to officer role.
     */
    public function promotionToOfficer(): static
    {
        $officerRoles = ['vice_president', 'secretary', 'treasurer'];

        return $this->state(fn (array $attributes) => [
            'old_role' => 'member',
            'new_role' => fake()->randomElement($officerRoles),
            'change_reason' => fake()->randomElement([
                'Promoted to officer role for demonstrated leadership',
                'Elected to officer position by club members',
                'Appointed to officer role due to exceptional service',
                'Selected for officer position based on skills and commitment',
            ]),
        ]);
    }

    /**
     * Create a demotion from officer to member.
     */
    public function demotionToMember(): static
    {
        $officerRoles = ['president', 'vice_president', 'secretary', 'treasurer'];

        return $this->state(fn (array $attributes) => [
            'old_role' => fake()->randomElement($officerRoles),
            'new_role' => 'member',
            'change_reason' => fake()->randomElement([
                'Stepped down from officer role due to time constraints',
                'Requested demotion to focus on academic priorities',
                'Officer term completed, returned to member status',
                'Role adjustment due to changing circumstances',
            ]),
        ]);
    }

    /**
     * Create a lateral move between officer roles.
     */
    public function lateralMove(): static
    {
        $officerRoles = ['vice_president', 'secretary', 'treasurer'];
        $oldRole = fake()->randomElement($officerRoles);
        $availableRoles = array_filter($officerRoles, fn ($role) => $role !== $oldRole);
        $newRole = fake()->randomElement($availableRoles);

        return $this->state(fn (array $attributes) => [
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'change_reason' => fake()->randomElement([
                'Role reassignment to better match skills and interests',
                'Organizational restructuring for improved efficiency',
                'Officer role rotation for experience development',
                'Strategic role adjustment for club development',
            ]),
        ]);
    }

    /**
     * Create a role change with specific duration.
     */
    public function withDuration(int $days): static
    {
        $startedAt = fake()->dateTimeBetween('-2 years', "-{$days} days");
        $endedAt = (clone $startedAt)->addDays($days);

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Create a recent role change.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'ended_at' => null, // Recent changes are typically still active
        ]);
    }

    /**
     * Create a historical role change.
     */
    public function historical(): static
    {
        $startedAt = fake()->dateTimeBetween('-2 years', '-6 months');
        $endedAt = fake()->dateTimeBetween($startedAt, '-3 months');

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Create a role change by a specific student.
     */
    public function changedBy(Student $changer): static
    {
        return $this->state(fn (array $attributes) => [
            'changed_by' => $changer->id,
        ]);
    }

    /**
     * Create a role change with detailed reason.
     */
    public function withDetailedReason(string $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'change_reason' => $reason,
        ]);
    }

    /**
     * Create a sequence of role changes for a member's progression.
     */
    public function memberProgression(): static
    {
        return $this->state(fn (array $attributes) => [
            'old_role' => null,
            'new_role' => 'member',
            'change_reason' => 'Initial membership approval and role assignment',
            'started_at' => fake()->dateTimeBetween('-2 years', '-18 months'),
            'ended_at' => fake()->dateTimeBetween('-15 months', '-12 months'),
        ]);
    }
}
