<?php

namespace Database\Factories\Modules\Engagement\Models;

use App\Models\Student;
use App\Modules\Engagement\Models\Club;
use App\Modules\Engagement\Models\ClubMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory
{
    protected $model = ClubMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roles = ['president', 'vice_president', 'secretary', 'treasurer', 'member'];
        $statuses = ['active', 'pending', 'rejected', 'left', 'banned'];

        $role = fake()->randomElement($roles);
        $status = fake()->randomElement($statuses);

        // Generate role-specific responsibilities
        $responsibilities = $this->generateResponsibilities($role);

        // Generate application notes for pending/rejected applications
        $applicationNotes = null;
        if (in_array($status, ['pending', 'rejected'])) {
            $applicationNotes = fake()->paragraph();
        }

        // Set appropriate dates based on status
        $joinedAt = null;
        $leftAt = null;
        $lastActiveAt = null;

        if ($status === 'active') {
            $joinedAt = fake()->dateTimeBetween('-2 years', '-1 week');
            $lastActiveAt = fake()->dateTimeBetween($joinedAt, 'now');
        } elseif ($status === 'left') {
            $joinedAt = fake()->dateTimeBetween('-2 years', '-6 months');
            $leftAt = fake()->dateTimeBetween($joinedAt, '-1 week');
            $lastActiveAt = fake()->dateTimeBetween($joinedAt, $leftAt);
        }

        return [
            'club_id' => Club::factory(),
            'student_id' => Student::factory(),
            'role' => $role,
            'status' => $status,
            'application_notes' => $applicationNotes,
            'approved_by' => null, // Will be set by states if needed
            'responsibilities' => $responsibilities,
            'participation_score' => fake()->numberBetween(0, 100),
            'last_active_at' => $lastActiveAt,
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
        ];
    }

    /**
     * Generate role-specific responsibilities.
     */
    private function generateResponsibilities(string $role): array
    {
        $responsibilityMap = [
            'president' => [
                'Lead club meetings and activities',
                'Represent the club in official matters',
                'Oversee club operations and strategic planning',
                'Coordinate with other club officers',
                'Manage club budget and resources',
            ],
            'vice_president' => [
                'Assist the president in club operations',
                'Lead meetings in president\'s absence',
                'Coordinate special projects and events',
                'Support member recruitment efforts',
            ],
            'secretary' => [
                'Maintain meeting minutes and records',
                'Handle club correspondence',
                'Manage membership records',
                'Coordinate communication with members',
            ],
            'treasurer' => [
                'Manage club finances and budget',
                'Track expenses and income',
                'Prepare financial reports',
                'Handle fundraising activities',
            ],
            'member' => [
                'Participate in club activities and meetings',
                'Support club initiatives and events',
                'Contribute to club goals and objectives',
            ],
        ];

        $baseResponsibilities = $responsibilityMap[$role] ?? $responsibilityMap['member'];

        // Randomly select 2-4 responsibilities
        $numResponsibilities = fake()->numberBetween(2, min(4, count($baseResponsibilities)));

        return fake()->randomElements($baseResponsibilities, $numResponsibilities);
    }

    /**
     * Indicate that the member is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'joined_at' => fake()->dateTimeBetween('-2 years', '-1 week'),
            'last_active_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'left_at' => null,
        ]);
    }

    /**
     * Indicate that the member is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'application_notes' => fake()->paragraph(),
            'approved_by' => null,
            'joined_at' => null,
            'last_active_at' => null,
            'left_at' => null,
        ]);
    }

    /**
     * Indicate that the member was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'application_notes' => fake()->paragraph(),
            'approved_by' => null,
            'joined_at' => null,
            'last_active_at' => null,
            'left_at' => null,
        ]);
    }

    /**
     * Indicate that the member has left the club.
     */
    public function left(): static
    {
        $joinedAt = fake()->dateTimeBetween('-2 years', '-6 months');
        $leftAt = fake()->dateTimeBetween($joinedAt, '-1 week');

        return $this->state(fn (array $attributes) => [
            'status' => 'left',
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
            'last_active_at' => fake()->dateTimeBetween($joinedAt, $leftAt),
        ]);
    }

    /**
     * Indicate that the member is banned.
     */
    public function banned(): static
    {
        $joinedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $leftAt = fake()->dateTimeBetween($joinedAt, 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
            'last_active_at' => fake()->dateTimeBetween($joinedAt, $leftAt),
        ]);
    }

    /**
     * Create a president member.
     */
    public function president(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'president',
            'status' => 'active',
            'responsibilities' => [
                'Lead club meetings and activities',
                'Represent the club in official matters',
                'Oversee club operations and strategic planning',
                'Coordinate with other club officers',
                'Manage club budget and resources',
            ],
            'participation_score' => fake()->numberBetween(80, 100),
            'joined_at' => fake()->dateTimeBetween('-2 years', '-6 months'),
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a vice president member.
     */
    public function vicePresident(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'vice_president',
            'status' => 'active',
            'responsibilities' => [
                'Assist the president in club operations',
                'Lead meetings in president\'s absence',
                'Coordinate special projects and events',
                'Support member recruitment efforts',
            ],
            'participation_score' => fake()->numberBetween(70, 95),
            'joined_at' => fake()->dateTimeBetween('-2 years', '-3 months'),
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a secretary member.
     */
    public function secretary(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'secretary',
            'status' => 'active',
            'responsibilities' => [
                'Maintain meeting minutes and records',
                'Handle club correspondence',
                'Manage membership records',
                'Coordinate communication with members',
            ],
            'participation_score' => fake()->numberBetween(60, 90),
            'joined_at' => fake()->dateTimeBetween('-18 months', '-2 months'),
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a treasurer member.
     */
    public function treasurer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'treasurer',
            'status' => 'active',
            'responsibilities' => [
                'Manage club finances and budget',
                'Track expenses and income',
                'Prepare financial reports',
                'Handle fundraising activities',
            ],
            'participation_score' => fake()->numberBetween(65, 90),
            'joined_at' => fake()->dateTimeBetween('-18 months', '-2 months'),
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a regular member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'member',
            'status' => 'active',
            'responsibilities' => [
                'Participate in club activities and meetings',
                'Support club initiatives and events',
                'Contribute to club goals and objectives',
            ],
            'participation_score' => fake()->numberBetween(20, 80),
            'joined_at' => fake()->dateTimeBetween('-1 year', '-1 week'),
            'last_active_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
        ]);
    }

    /**
     * Create a member with high participation.
     */
    public function highParticipation(): static
    {
        return $this->state(fn (array $attributes) => [
            'participation_score' => fake()->numberBetween(80, 100),
            'last_active_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    /**
     * Create a member with low participation.
     */
    public function lowParticipation(): static
    {
        return $this->state(fn (array $attributes) => [
            'participation_score' => fake()->numberBetween(0, 30),
            'last_active_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
        ]);
    }

    /**
     * Create a member with detailed application notes.
     */
    public function withDetailedApplication(): static
    {
        $motivations = [
            'I am passionate about this field and want to contribute to the club\'s mission.',
            'I have relevant experience and skills that would benefit the organization.',
            'I am looking to develop my leadership abilities and learn from other members.',
            'I want to be part of a community that shares my interests and values.',
            'I believe I can help the club achieve its goals and grow its impact.',
        ];

        return $this->state(fn (array $attributes) => [
            'application_notes' => fake()->randomElement($motivations).' '.fake()->paragraph(),
        ]);
    }

    /**
     * Create a member approved by a specific student.
     */
    public function approvedBy(Student $approver): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_by' => $approver->id,
            'status' => 'active',
            'joined_at' => fake()->dateTimeBetween('-1 year', '-1 week'),
            'last_active_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
