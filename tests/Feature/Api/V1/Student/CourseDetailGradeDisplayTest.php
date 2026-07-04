<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Services\CourseCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Student portal single-course "Grades" tab scheme display
 * (metropolia-grading-display issue 05). Mirrors the cockpit Scores tab
 * scheme test (issue 02) at the student-facing course-detail endpoint.
 */
beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->unit = Unit::factory()->create();

    $this->gateFailScheme = [
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [
            ['code' => 'ASSIGNMENT', 'gate' => ['min_pct' => 40], 'conversion' => null],
            [
                'code' => 'EXAM',
                'gate' => ['min_pct' => 40],
                'conversion' => ['type' => 'linear', 'min_pct' => 40, 'max_pct' => 88, 'min_grade' => 1, 'max_grade' => 5],
            ],
        ],
    ];

    $this->makeSyllabus = function (?array $gradingScheme) {
        return SyllabusTemplate::factory()->create([
            'unit_id' => $this->unit->id,
            'grading_scheme' => $gradingScheme,
        ]);
    };

    $this->makeOffering = function (SyllabusTemplate $syllabus) {
        return CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'syllabus_template_id' => $syllabus->id,
            'course_status' => 'in_progress',
            'enrollment_status' => 'open',
            'current_enrollment' => 0,
            'is_canvas_synced' => false,
        ]);
    };

    $this->enrollStudent = function (CourseOffering $offering, array $recordOverrides = []) {
        $student = Student::factory()->forCampus($this->campus)->create([
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3.0,
            'attempt_number' => 1,
            'is_retake' => false,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
        ]);

        AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'credit_hours' => 3.0,
            'credit_points' => 3.0,
            'enrollment_date' => now()->toDateString(),
        ], $recordOverrides));

        return $student;
    };

    $this->scoreComponent = function (CourseOffering $offering, SyllabusTemplate $syllabus, Student $student, string $code, float $weight, float $percentage) {
        $component = AssessmentComponent::factory()->create([
            'syllabus_template_id' => $syllabus->id,
            'weight' => $weight,
            'code' => $code,
        ]);
        $detail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 1,
        ]);
        AssessmentComponentDetailScore::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'assessment_component_detail_id' => $detail->id,
            'percentage_score' => $percentage,
            'score_status' => 'final',
            'score_excluded' => false,
        ]);
    };

    $this->getCourseDetail = function (Student $student, CourseOffering $offering) {
        Sanctum::actingAs($student);

        return $this->getJson(route('v1.student.course-registration.course-detail-legacy', ['courseOfferingId' => $offering->id]));
    };
});

it('includes grade_display on total_grade for a finalized scheme offering', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering, ['is_passed' => true]);

    ($this->scoreComponent)($offering, $syllabus, $student, 'ASSIGNMENT', 0, 55.0);
    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 88.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getCourseDetail)($student, $offering);

    $response->assertOk()
        ->assertJsonPath('data.grades.total_grade.grade_display.scheme_engine', 'metropolia_v1')
        ->assertJsonPath('data.grades.total_grade.grade_display.scale', 'numeric_0_5')
        ->assertJsonPath('data.grades.total_grade.grade_display.final_label', '5')
        ->assertJsonPath('data.grades.total_grade.grade_display.pass_status', 'passed');

    $components = collect($response->json('data.grades.total_grade.grade_display.components'))->keyBy('code');
    expect($components['ASSIGNMENT']['requirement_status'])->toBe('passed')
        ->and((float) $components['EXAM']['converted_grade'])->toBe(5.0)
        ->and($components['EXAM']['requirement_status'])->toBe('passed');
});

it('shows a failed pass_status and requirement "not met" for a gate-fail student instead of a derived PASS from raw percentage', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering, ['is_passed' => false]);

    // High EXAM score, fails the ASSIGNMENT gate (< 40%).
    ($this->scoreComponent)($offering, $syllabus, $student, 'ASSIGNMENT', 0, 30.0);
    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 88.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getCourseDetail)($student, $offering);

    $response->assertOk()
        ->assertJsonPath('data.grades.total_grade.grade_display.pass_status', 'failed');

    $components = collect($response->json('data.grades.total_grade.grade_display.components'))->keyBy('code');
    expect($components['ASSIGNMENT']['requirement_status'])->toBe('failed');
});

it('shows no requirement indicator for a component without a passing requirement', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [
            ['code' => 'EXAM', 'conversion' => ['type' => 'direct', 'max_grade' => 5]],
        ],
    ];
    $syllabus = ($this->makeSyllabus)($scheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 80.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getCourseDetail)($student, $offering);

    $components = collect($response->json('data.grades.total_grade.grade_display.components'))->keyBy('code');
    expect($components['EXAM']['requirement_status'])->toBeNull();
});

it('keeps the raw grid unchanged with a null grade_display for an unfinalized scheme offering', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 82.5);

    $response = ($this->getCourseDetail)($student, $offering);

    $response->assertOk()
        ->assertJsonPath('data.grades.total_grade.grade_display', null);
});

it('renders default-weighted offerings byte-identical: no grade_display key at all', function () {
    $syllabus = ($this->makeSyllabus)(null);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 75.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getCourseDetail)($student, $offering);

    $response->assertOk()
        ->assertJsonPath('data.grades.total_grade.final_percentage', '75.00');

    expect(array_key_exists('grade_display', $response->json('data.grades.total_grade')))->toBeFalse();
});
