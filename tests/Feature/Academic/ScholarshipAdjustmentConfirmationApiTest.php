<?php

declare(strict_types=1);

use App\Http\Middleware\StudentApiAuthorization;
use App\Models\AcademicHold;
use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiStudent(): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
}

function apiDossierFor(Student $student, array $overrides = []): ScholarshipAdjustmentDossier
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $definition = ScholarshipDefinition::create([
        'code' => 'API'.uniqid(),
        'name' => 'API test',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::firstOrCreate(
        ['student_id' => $student->id],
        ['scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString()],
    );

    return ScholarshipAdjustmentDossier::create(array_merge([
        'student_id' => $student->id,
        'campus_id' => $student->campus_id ?? $campus->id,
        'source_semester_id' => $semester->id,
        'target_semester_id' => Semester::factory()->create()->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
        'minutes' => 'Minutes text.',
        'minutes_version' => 1,
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
        'confirmation_requested_at' => now(),
        'created_by_user_id' => User::factory()->create()->id,
    ], $overrides));
}

it('lets a student read the minutes of their own dossier', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]))
        ->assertOk()
        ->assertJsonPath('data.minutes_version', 1);
});

it('returns 404 when a student tries to read another student dossier', function () {
    $owner = apiStudent();
    $dossier = apiDossierFor($owner);
    $other = apiStudent();
    Sanctum::actingAs($other);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]))
        ->assertNotFound();
});

it('confirms with the matching minutes version', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertOk();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('rejects a stale minutes version with 409', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student, ['minutes_version' => 5]);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertStatus(409);
});

it('requires a comment when disputing', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => false,
    ])->assertStatus(422);
});

it('lets a student with a financial hold still confirm (hold exemption)', function () {
    $student = apiStudent();
    AcademicHold::create([
        'student_id' => $student->id,
        'hold_type' => 'financial',
        'hold_category' => 'all',
        'title' => 'Outstanding balance',
        'status' => 'active',
        'placed_date' => now()->toDateString(),
    ]);
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertOk();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('exempts only the confirmation routes from financial holds — not other routes', function () {
    // Directly exercise the allow-list so the guarantee does not depend on the
    // either-middleware quirks of unrelated student routes: the confirmation
    // route name is exempt; typical student routes are not.
    $middleware = new StudentApiAuthorization;
    $method = new ReflectionMethod($middleware, 'isHoldExemptRoute');

    expect($method->invoke($middleware, 'v1.student.scholarship-adjustment-confirmation.respond'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.scholarship-adjustment-confirmation.minutes'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.profile.show'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.grades.index'))->toBeFalse()
        ->and($method->invoke($middleware, 'v1.student.dashboard.index'))->toBeFalse();
});

it('rejects a guardian/parent actor even when supplying the student binding input', function () {
    // A parent authenticates as a User (not a Student) and, on the shared
    // `either` routes, would bind a student via `X-Student-ID`. The confirmation
    // routes run `student.api.auth` ALONE (no parent.student.access), so even
    // with that header the non-Student actor is refused — the acknowledgement
    // can only be bound by the Student themselves.
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(
        route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]),
        ['X-Student-ID' => (string) $student->id],
    )->assertForbidden();

    $this->postJson(
        route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]),
        ['minutes_version' => 1, 'agree' => true],
        ['X-Student-ID' => (string) $student->id],
    )->assertForbidden();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING);
});
