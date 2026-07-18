<?php

declare(strict_types=1);

use App\Models\ApplicationGuardian;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function rerunGuardianOwnershipMigrations(): void
{
    Schema::disableForeignKeyConstraints();
    Schema::drop('guardian_access_grants');
    Schema::drop('student_guardian_relationships');
    Schema::enableForeignKeyConstraints();

    $registryMigration = require database_path('migrations/2026_07_18_150300_create_student_guardian_relationships_table.php');
    $identityMigration = require database_path('migrations/2026_07_18_150301_create_guardian_access_grants_table.php');
    $registryMigration->up();
    $identityMigration->up();
}

it('backfills existing Guardian relationships and access levels without duplicates', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $primary = ParentProfile::factory()->create([
        'user_id' => User::factory()->create(['type' => UserType::PARENT])->id,
        'full_name' => 'Legacy Primary',
        'email_snapshot' => 'legacy-primary@example.test',
    ]);
    $secondary = ParentProfile::factory()->create([
        'user_id' => User::factory()->create(['type' => UserType::PARENT])->id,
        'full_name' => 'Legacy Secondary',
        'email_snapshot' => 'legacy-secondary@example.test',
    ]);
    $now = now();

    DB::table('parent_student')->insert([
        [
            'parent_id' => $primary->id,
            'student_id' => $student->id,
            'relationship' => 'mother',
            'is_primary' => true,
            'access_level' => 'full_read',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'parent_id' => $secondary->id,
            'student_id' => $student->id,
            'relationship' => 'father',
            'is_primary' => false,
            'access_level' => 'read_only',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    rerunGuardianOwnershipMigrations();

    $relationships = DB::table('student_guardian_relationships')->where('student_id', $student->id)->get();
    $grants = DB::table('guardian_access_grants')->where('student_id', $student->id)->get();

    expect($relationships)->toHaveCount(2)
        ->and($relationships->where('is_primary', true))->toHaveCount(1)
        ->and($relationships->pluck('legacy_parent_student_id')->filter()->unique())->toHaveCount(2)
        ->and($grants)->toHaveCount(2)
        ->and($grants->pluck('guardian_relationship_id')->unique())->toHaveCount(2)
        ->and($grants->firstWhere('parent_id', $primary->id)->access_level)->toBe('full_read')
        ->and($grants->firstWhere('parent_id', $secondary->id)->access_level)->toBe('read_only');
});

it('restores no-email Guardians from approved Applications without duplicating account-backed relationships', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $application = StudentApplication::factory()->enrolled()->create(['student_id' => $student->id]);
    ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Historical Offline Guardian',
        'relationship' => 'mother',
        'email' => null,
    ]);
    ApplicationGuardian::factory()->forApplication($application)->create([
        'full_name' => 'Historical Portal Guardian',
        'relationship' => 'father',
        'email' => 'historical-portal@example.test',
    ]);
    $profile = ParentProfile::factory()->create([
        'user_id' => User::factory()->create(['type' => UserType::PARENT])->id,
        'full_name' => 'Historical Portal Guardian',
        'email_snapshot' => 'historical-portal@example.test',
    ]);
    DB::table('parent_student')->insert([
        'parent_id' => $profile->id,
        'student_id' => $student->id,
        'relationship' => 'father',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    rerunGuardianOwnershipMigrations();

    $relationships = DB::table('student_guardian_relationships')->where('student_id', $student->id)->get();
    $offline = $relationships->firstWhere('full_name', 'Historical Offline Guardian');
    $portal = $relationships->firstWhere('full_name', 'Historical Portal Guardian');
    $grant = DB::table('guardian_access_grants')->where('student_id', $student->id)->first();

    expect($relationships)->toHaveCount(2)
        ->and((bool) $offline->is_primary)->toBeTrue()
        ->and((bool) $portal->is_primary)->toBeFalse()
        ->and($grant)->not->toBeNull()
        ->and($grant->guardian_relationship_id)->toBe($portal->id);
});

it('preserves an unmatched legacy primary when an Application Guardian is already primary', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $application = StudentApplication::factory()->enrolled()->create(['student_id' => $student->id]);
    ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Offline Application Guardian',
        'relationship' => 'mother',
        'email' => null,
    ]);
    $profile = ParentProfile::factory()->create([
        'user_id' => User::factory()->create(['type' => UserType::PARENT])->id,
        'full_name' => 'Staff Assigned Portal Guardian',
        'email_snapshot' => 'staff-assigned@example.test',
    ]);
    DB::table('parent_student')->insert([
        'parent_id' => $profile->id,
        'student_id' => $student->id,
        'relationship' => 'father',
        'is_primary' => true,
        'access_level' => 'full_read',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    rerunGuardianOwnershipMigrations();

    $relationships = DB::table('student_guardian_relationships')->where('student_id', $student->id)->get();
    $portalRelationship = $relationships->firstWhere('email', 'staff-assigned@example.test');
    $grant = DB::table('guardian_access_grants')->where('parent_id', $profile->id)->first();

    expect($relationships)->toHaveCount(2)
        ->and($relationships->where('is_primary', true))->toHaveCount(1)
        ->and($portalRelationship)->not->toBeNull()
        ->and((bool) $portalRelationship->is_primary)->toBeFalse()
        ->and($grant)->not->toBeNull()
        ->and($grant->guardian_relationship_id)->toBe($portalRelationship->id)
        ->and($grant->access_level)->toBe('full_read');
});
