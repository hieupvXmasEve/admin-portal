<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->lecturerUser = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $this->lecturer = Lecture::factory()->create([
        'user_id' => $this->lecturerUser->id,
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);
    $this->unit = Unit::factory()->create([
        'code' => 'DATABASES',
        'name' => 'Relational databases',
        'credit_points' => 3.0,
    ]);

    $this->makeStudent = function (string $studentCode): Student {
        return Student::factory()->forCampus($this->campus)->create([
            'student_id' => $studentCode,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);
    };

    $this->student = ($this->makeStudent)('S1001');

    $this->makeSyllabus = function (string $title = 'Metropolia: Relational databases'): SyllabusTemplate {
        return SyllabusTemplate::create([
            'unit_id' => $this->unit->id,
            'title' => $title,
            'version' => '1.0',
            'total_hours' => 60,
            'total_sessions' => 15,
            'learning_outcomes' => [],
            'grading_criteria' => [],
            'required_materials' => [],
            'is_default' => true,
            'is_active' => true,
            'min_grade_threshold' => 60,
            'grading_scheme' => [
                'engine' => 'metropolia_v1',
                'scale' => 'pass_fail',
                'components' => [
                    ['code' => 'ASSIGNMENT', 'label' => 'Assignments', 'conversion' => ['type' => 'pass_fail', 'min_pct' => 80]],
                ],
            ],
        ]);
    };

    $this->makeOffering = function (SyllabusTemplate $syllabus, array $overrides = []): CourseOffering {
        $offering = CourseOffering::factory()->create(array_merge([
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'lecture_id' => $this->lecturer->id,
            'campus_id' => $this->campus->id,
            'syllabus_template_id' => $syllabus->id,
            'grading_type' => 'pass_fail',
            'course_status' => 'in_progress',
            'enrollment_status' => 'open',
            'is_canvas_synced' => false,
        ], $overrides));

        ClassSession::factory()->create([
            'course_offering_id' => $offering->id,
            'lecture_id' => $this->lecturer->id,
            'status' => 'scheduled',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        return $offering;
    };

    $this->makeAssignmentDetail = function (SyllabusTemplate $syllabus): AssessmentComponentDetail {
        $component = AssessmentComponent::factory()->create([
            'syllabus_template_id' => $syllabus->id,
            'name' => 'Assignments',
            'code' => 'ASSIGNMENT',
            'type' => 'assignment',
            'weight' => 100,
        ]);

        return AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'name' => 'Assignments',
            'weight' => 100,
            'max_points' => 100,
        ]);
    };

    $this->enrollStudentWithStaleRecord = function (CourseOffering $offering, ?Student $student = null): AcademicRecord {
        $student ??= $this->student;

        CourseRegistration::query()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3.0,
            'credit_points' => 3.0,
            'attempt_number' => 1,
            'is_retake' => false,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
        ]);

        return AcademicRecord::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'final_percentage' => 0,
            'final_letter_grade' => 'F',
            'grade_points' => 0,
            'quality_points' => 0,
            'credit_hours' => 3.0,
            'credit_hours_earned' => 0,
            'credit_points' => 3.0,
            'credit_points_earned' => 0,
            'grade_status' => 'in_progress',
            'completion_status' => 'in_progress',
            'enrollment_date' => now()->toDateString(),
            'is_passed' => false,
            'grade_breakdown' => ['engine' => 'default_weighted_percentage'],
        ]);
    };
});

it('returns compact gradebook items and matrix cells for a lecturer course', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    ($this->enrollStudentWithStaleRecord)($offering);

    AssessmentComponentDetailScore::query()->create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $this->student->id,
        'course_offering_id' => $offering->id,
        'points_earned' => 80,
        'percentage_score' => 80,
        'letter_grade' => 'B',
        'status' => 'graded',
        'graded_by_lecture_id' => $this->lecturer->id,
    ]);

    Sanctum::actingAs($this->lecturer);

    $response = $this->getJson("/api/v1/lecturer/courses/{$offering->id}/gradebook");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.students', 1)
        ->assertJsonPath('data.summary.items', 1)
        ->assertJsonPath('data.summary.graded_cells', 1)
        ->assertJsonPath('data.items.0.detail_id', $detail->id)
        ->assertJsonPath('data.items.0.component_code', 'ASSIGNMENT')
        ->assertJsonPath('data.items.0.max_points', 100)
        ->assertJsonPath('data.students.0.student_id', $this->student->id)
        ->assertJsonPath('data.students.0.cells.0.detail_id', $detail->id)
        ->assertJsonPath('data.students.0.cells.0.points_earned', 80);
});

it('does not create assessment details while reading a gradebook', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'name' => 'Unconfigured assessment',
        'weight' => 100,
    ]);

    Sanctum::actingAs($this->lecturer);

    $this->getJson("/api/v1/lecturer/courses/{$offering->id}/gradebook")
        ->assertOk()
        ->assertJsonPath('data.items', []);

    expect($component->details()->count())->toBe(0);
});

it('saves matrix scores in one request and refreshes the manual course aggregate', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $record = ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/gradebook/scores", [
        'scores' => [
            [
                'detail_id' => $detail->id,
                'student_id' => $this->student->id,
                'points_earned' => 80,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_saved', 1)
        ->assertJsonPath('data.aggregated', true);

    $score = AssessmentComponentDetailScore::query()->firstOrFail();

    expect($score->assessment_component_detail_id)->toBe($detail->id)
        ->and($score->student_id)->toBe($this->student->id)
        ->and($score->course_offering_id)->toBe($offering->id)
        ->and($score->points_earned)->toBe(80)
        ->and((float) $score->percentage_score)->toBe(80.0)
        ->and($score->status)->toBe('graded')
        ->and($score->graded_by_lecture_id)->toBe($this->lecturer->id);

    $record->refresh();

    expect($record->final_letter_grade)->toBe('P')
        ->and($record->grade_breakdown['engine'])->toBe('metropolia_v1')
        ->and($record->grade_breakdown['passed'])->toBeTrue();
});

it('rejects gradebook score cells from another syllabus template', function () {
    $currentSyllabus = ($this->makeSyllabus)('Current Metropolia template');
    $oldSyllabus = ($this->makeSyllabus)('Old Metropolia template');
    $offering = ($this->makeOffering)($currentSyllabus);
    $oldDetail = ($this->makeAssignmentDetail)($oldSyllabus);
    ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/gradebook/scores", [
        'scores' => [
            [
                'detail_id' => $oldDetail->id,
                'student_id' => $this->student->id,
                'points_earned' => 80,
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.field', 'scores.0.detail_id');

    expect(AssessmentComponentDetailScore::query()->count())->toBe(0);
});

it('rejects over max points without saving any gradebook score cells', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $secondStudent = ($this->makeStudent)('S1002');
    ($this->enrollStudentWithStaleRecord)($offering);
    ($this->enrollStudentWithStaleRecord)($offering, $secondStudent);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/gradebook/scores", [
        'scores' => [
            [
                'detail_id' => $detail->id,
                'student_id' => $this->student->id,
                'points_earned' => 80,
            ],
            [
                'detail_id' => $detail->id,
                'student_id' => $secondStudent->id,
                'points_earned' => 101,
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.field', 'scores.1.points_earned');

    expect(AssessmentComponentDetailScore::query()->count())->toBe(0);
});

it('blocks lecturers who do not teach the course offering', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $otherLecturer = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);

    Sanctum::actingAs($otherLecturer);

    $response = $this->getJson("/api/v1/lecturer/courses/{$offering->id}/gradebook");

    $response->assertForbidden()
        ->assertJsonPath('success', false);
});
