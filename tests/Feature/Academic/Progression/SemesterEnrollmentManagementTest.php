<?php

declare(strict_types=1);

use App\Http\Middleware\CheckCampusSelected;
use App\Models\AcademicHold;
use App\Models\Campus;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the trimmed SemesterEnrollmentController
 * (show + generateEnrollments only) before it moves off
 * app/Http/Controllers/Web into owned Academic Progression
 * (zero-migration-debt-closure phase 4b).
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    actingAs(User::factory()->create());

    $this->intakeSemester = Semester::factory()->create();
});

/** @return array<string, mixed> */
function eligibleStudentAttributes(Campus $campus, Semester $intakeSemester, array $overrides = []): array
{
    return array_merge([
        'campus_id' => $campus->id,
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $intakeSemester->id,
    ], $overrides);
}

it('shows the semester enrollment overview with campus-scoped stats', function (): void {
    $semester = Semester::factory()->create();
    $otherCampus = Campus::factory()->create();

    $eligibleUnenrolled = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    $enrolledStudent = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    Enrollment::factory()->create([
        'student_id' => $enrolledStudent->id,
        'semester_id' => $semester->id,
        'curriculum_version_id' => $enrolledStudent->curriculum_version_id,
        'semester_number' => 2,
        'status' => 'in_progress',
    ]);

    // Different campus enrollment must not leak into this campus's stats.
    $otherCampusStudent = Student::factory()->create(eligibleStudentAttributes($otherCampus, $this->intakeSemester));
    Enrollment::factory()->create([
        'student_id' => $otherCampusStudent->id,
        'semester_id' => $semester->id,
        'curriculum_version_id' => $otherCampusStudent->curriculum_version_id,
    ]);

    expect($eligibleUnenrolled)->not->toBeNull();

    $this->get(route('semesters.enrollment.show', $semester))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Semesters/Enrollment')
            ->where('semester.id', $semester->id)
            ->where('enrollmentStats.total_enrolled', 1)
            ->where('campusStats.campus_name', $this->campus->name)
            ->where('campusStats.total_eligible_students', 3)
            ->where('campusStats.enrolled_students', 1)
            ->where('campusStats.not_enrolled_students', 2)
        );
});

it('generates enrollments for eligible students and skips ineligible ones', function (): void {
    $semester = Semester::factory()->create();

    $eligible = Student::factory()->count(2)->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));

    // Already enrolled this semester -> excluded from eligibility entirely.
    $alreadyEnrolled = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    Enrollment::factory()->create([
        'student_id' => $alreadyEnrolled->id,
        'semester_id' => $semester->id,
        'curriculum_version_id' => $alreadyEnrolled->curriculum_version_id,
    ]);

    // Active registration hold -> excluded from eligibility entirely.
    $held = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    AcademicHold::create([
        'student_id' => $held->id,
        'hold_type' => 'administrative',
        'hold_category' => 'registration',
        'title' => 'Outstanding balance',
        'status' => 'active',
        'placed_date' => now(),
    ]);

    // Different campus -> excluded.
    $otherCampus = Campus::factory()->create();
    Student::factory()->create(eligibleStudentAttributes($otherCampus, $this->intakeSemester));

    $response = $this->postJson(route('api.semesters.enrollment.generate', $semester))
        ->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonPath('enrollments_created', 2);

    foreach ($eligible as $student) {
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'semester_number' => 1,
        ]);
    }

    $this->assertDatabaseCount('enrollments', 3); // 2 new + the pre-existing one
});

it('rejects generating enrollments without a selected campus', function (): void {
    $this->withoutMiddleware(CheckCampusSelected::class);
    session(['current_campus_id' => null]);
    $semester = Semester::factory()->create();

    $this->postJson(route('api.semesters.enrollment.generate', $semester))
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No campus selected. Please select a campus first.');
});

it('reports no eligible students without creating anything', function (): void {
    $semester = Semester::factory()->create();
    Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester, [
        'status' => 'active', // not an eligible intake status
    ]));

    $this->postJson(route('api.semesters.enrollment.generate', $semester))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No eligible students found for enrollment in the selected campus');

    $this->assertDatabaseCount('enrollments', 0);
});

it('increments the next semester_number from the student\'s latest enrollment', function (): void {
    $priorSemester = Semester::factory()->create();
    $targetSemester = Semester::factory()->create();

    $student = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $priorSemester->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'semester_number' => 3,
    ]);

    $this->postJson(route('api.semesters.enrollment.generate', $targetSemester))
        ->assertOk()
        ->assertJsonPath('enrollments_created', 1);

    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'semester_id' => $targetSemester->id,
        'semester_number' => 4,
    ]);
});

it('skips a student whose next semester_number would exceed the maximum', function (): void {
    $priorSemester = Semester::factory()->create();
    $targetSemester = Semester::factory()->create();

    $student = Student::factory()->create(eligibleStudentAttributes($this->campus, $this->intakeSemester));
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $priorSemester->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'semester_number' => 8,
    ]);

    $this->postJson(route('api.semesters.enrollment.generate', $targetSemester))
        ->assertOk()
        ->assertJsonPath('enrollments_created', 0);

    $this->assertDatabaseMissing('enrollments', [
        'student_id' => $student->id,
        'semester_id' => $targetSemester->id,
    ]);
});
