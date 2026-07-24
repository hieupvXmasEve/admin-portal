<?php

declare(strict_types=1);

use App\Models\GpaCalculation;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns finalized GPA history through the established student API contract', function () {
    $student = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $semester = Semester::factory()->create(['name' => 'Spring 2026']);

    GpaCalculation::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 3.5,
        'cumulative_gpa' => 3.25,
        'semester_credit_points' => 15,
        'cumulative_credit_points' => 30,
        'semester_credit_points_earned' => 15,
        'cumulative_credit_points_earned' => 30,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
        'finalized_at' => now(),
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.academic-records.index'))
        ->assertOk()
        ->assertJsonPath('summary.latest_semester_gpa', '3.500')
        ->assertJsonPath('summary.cumulative_gpa', '3.250')
        ->assertJsonPath('summary.academic_standing', 'normal')
        ->assertJsonPath('summary.credits_earned', '30.00')
        ->assertJsonPath('history.0.semester_name', 'Spring 2026');
});

it('does not expose another student\'s finalized GPA history', function () {
    $student = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $otherStudent = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $semester = Semester::factory()->create();

    GpaCalculation::query()->create([
        'student_id' => $otherStudent->id,
        'semester_id' => $semester->id,
        'program_id' => $otherStudent->program_id,
        'semester_gpa' => 4,
        'cumulative_gpa' => 4,
        'semester_credit_points' => 15,
        'cumulative_credit_points' => 15,
        'semester_credit_points_earned' => 15,
        'cumulative_credit_points_earned' => 15,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.academic-records.index'))
        ->assertOk()
        ->assertJsonPath('history', [])
        ->assertJsonPath('summary.latest_semester_gpa', 0)
        ->assertJsonPath('summary.cumulative_gpa', 0);
});

it('preserves active parent proxy access to the selected student record', function () {
    $student = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $semester = Semester::factory()->create();

    GpaCalculation::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 3.75,
        'cumulative_gpa' => 3.75,
        'semester_credit_points' => 15,
        'cumulative_credit_points' => 15,
        'semester_credit_points_earned' => 15,
        'cumulative_credit_points_earned' => 15,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Portal Guardian',
        'relationship_type' => 'parent',
        'email' => 'portal-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.academic-records.index'), [
        'X-Student-ID' => $student->student_id,
    ])
        ->assertOk()
        ->assertJsonPath('summary.cumulative_gpa', '3.750');
});

it('rejects a parent proxy request for a student without an active grant', function () {
    $grantedStudent = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $ungrantedStudent = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);

    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $grantedStudent->id, [[
        'full_name' => 'Granted Guardian',
        'relationship_type' => 'parent',
        'email' => 'granted-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.academic-records.index'), [
        'X-Student-ID' => $ungrantedStudent->student_id,
    ])->assertForbidden();
});
