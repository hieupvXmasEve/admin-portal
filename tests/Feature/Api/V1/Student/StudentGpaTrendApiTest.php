<?php

declare(strict_types=1);

use App\Models\GpaCalculation;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Academic\StudentGpaTrendReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function studentGpaTrendApiStudent(): Student
{
    return Student::factory()->create([
        'user_id' => User::factory()->create()->id,
        'intake' => 2024,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
        'status' => 'active',
    ]);
}

function studentGpaTrendApiCalculation(Student $student, Semester $semester, float $gpa, ?string $createdAt = null): GpaCalculation
{
    $calculation = GpaCalculation::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'program_id' => $student->program_id,
        'semester_gpa' => $gpa,
        'cumulative_gpa' => $gpa,
        'semester_quality_points' => 300,
        'cumulative_quality_points' => 300,
        'semester_credit_points' => 3,
        'cumulative_credit_points' => 3,
        'semester_credit_points_earned' => 3,
        'cumulative_credit_points_earned' => 3,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    if ($createdAt !== null) {
        $calculation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }

    return $calculation;
}

it('returns only the authenticated student finalized GPA snapshots', function (): void {
    $student = studentGpaTrendApiStudent();
    $otherStudent = studentGpaTrendApiStudent();
    $firstSemester = Semester::factory()->create(['name' => 'First GPA Term', 'code' => 'GPA-FIRST']);
    $secondSemester = Semester::factory()->create(['name' => 'Second GPA Term', 'code' => 'GPA-SECOND']);
    $otherSemester = Semester::factory()->create(['name' => 'Other GPA Term', 'code' => 'GPA-OTHER']);
    studentGpaTrendApiCalculation($student, $firstSemester, 72.5);
    studentGpaTrendApiCalculation($student, $secondSemester, 82.5);
    studentGpaTrendApiCalculation($otherStudent, $otherSemester, 99.0);

    Sanctum::actingAs($student);

    $response = $this->getJson(route('v1.student.grades.gpa-trend', ['semester_count' => 8]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'GPA trend retrieved successfully')
        ->assertJsonCount(2, 'data.trend_data');

    expect(collect($response->json('data.trend_data'))->pluck('semester_code')->all())
        ->toContain('GPA-FIRST', 'GPA-SECOND')
        ->not->toContain('GPA-OTHER');
});

it('preserves the no-data GPA trend response', function (): void {
    Sanctum::actingAs(studentGpaTrendApiStudent());

    $this->getJson(route('v1.student.grades.gpa-trend'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.trend_data', [])
        ->assertJsonPath('data.trend_analysis.direction', 'no_data')
        ->assertJsonPath('data.predictions', []);
});

it('uses the current 100-point GPA scale when predicting the next semester', function (): void {
    $student = studentGpaTrendApiStudent();
    $firstSemester = Semester::factory()->create(['code' => 'GPA-PREDICT-1']);
    $secondSemester = Semester::factory()->create(['code' => 'GPA-PREDICT-2']);
    $thirdSemester = Semester::factory()->create(['code' => 'GPA-PREDICT-3']);
    studentGpaTrendApiCalculation($student, $firstSemester, 80.0, '2026-01-01 00:00:00');
    studentGpaTrendApiCalculation($student, $secondSemester, 90.0, '2026-02-01 00:00:00');
    studentGpaTrendApiCalculation($student, $thirdSemester, 100.0, '2026-03-01 00:00:00');

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.gpa-trend'))
        ->assertOk()
        ->assertJsonPath('data.trend_analysis.direction', 'improving')
        ->assertJsonPath('data.predictions.next_semester_prediction', 100)
        ->assertJsonPath('data.predictions.confidence', 'low');
});

it('validates the requested GPA trend window', function (): void {
    Sanctum::actingAs(studentGpaTrendApiStudent());

    $this->getJson(route('v1.student.grades.gpa-trend', ['semester_count' => 0]))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.field', 'semester_count');
});

it('preserves the GPA trend server error response', function (): void {
    $student = studentGpaTrendApiStudent();
    $this->mock(StudentGpaTrendReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('forStudent')->once()->andThrow(new RuntimeException('Reader unavailable'));
    });

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.gpa-trend'))
        ->assertServerError()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Failed to retrieve GPA trend')
        ->assertJsonPath('errors.0.code', 'SERVER_ERROR');
});

it('scopes GPA trend data to the selected student for an active parent proxy grant', function (): void {
    $student = studentGpaTrendApiStudent();
    $otherStudent = studentGpaTrendApiStudent();
    $studentSemester = Semester::factory()->create(['code' => 'GPA-PARENT']);
    $otherSemester = Semester::factory()->create(['code' => 'GPA-PARENT-OTHER']);
    studentGpaTrendApiCalculation($student, $studentSemester, 80.0);
    studentGpaTrendApiCalculation($otherStudent, $otherSemester, 95.0);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'GPA Trend Guardian',
        'relationship_type' => 'parent',
        'email' => 'gpa-trend-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.grades.gpa-trend'), ['X-Student-ID' => $student->student_id])
        ->assertOk()
        ->assertJsonCount(1, 'data.trend_data')
        ->assertJsonPath('data.trend_data.0.semester_code', 'GPA-PARENT');
});

it('rejects GPA trend reads through an ungranted parent proxy', function (): void {
    $grantedStudent = studentGpaTrendApiStudent();
    $ungrantedStudent = studentGpaTrendApiStudent();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $grantedStudent->id, [[
        'full_name' => 'Granted GPA Trend Guardian',
        'relationship_type' => 'parent',
        'email' => 'granted-gpa-trend-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.grades.gpa-trend'), ['X-Student-ID' => $ungrantedStudent->student_id])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'AUTHORIZATION_ERROR');
});
