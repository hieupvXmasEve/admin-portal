<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Identity\Models;

use App\Models\ParentProfile;
use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Modules\StudentRegistry\Models\StudentGuardianRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianAccessGrant>
 */
class GuardianAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_relationship_id' => StudentGuardianRelationship::factory(),
            'parent_id' => ParentProfile::factory(),
            'student_id' => static fn (array $attributes): int => (int) StudentGuardianRelationship::query()
                ->findOrFail($attributes['guardian_relationship_id'])
                ->student_id,
            'access_level' => 'read_only',
            'status' => GuardianAccessGrant::STATUS_ACTIVE,
            'granted_at' => now(),
        ];
    }
}
