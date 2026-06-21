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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CourseCompletionService::class);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->unit = Unit::factory()->create(['unit_type' => 'general', 'credit_points' => 3.0]);
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->makeSyllabus = function (?array $gradingScheme) {
        return SyllabusTemplate::create([
            'unit_id' => $this->unit->id,
            'title' => 'Test Syllabus',
            'version' => '1.0',
            'total_hours' => 60,
            'total_sessions' => 15,
            'learning_outcomes' => [],
            'grading_criteria' => [],
            'required_materials' => [],
            'is_default' => true,
            'is_active' => true,
            'min_grade_threshold' => 60,
            'grading_scheme' => $gradingScheme,
        ]);
    };

    $this->makeOffering = function (SyllabusTemplate $syllabus) {
        return CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'syllabus_template_id' => $syllabus->id,
            'is_canvas_synced' => false,
        ]);
    };

    $this->enrollStudent = function (CourseOffering $offering) {
        CourseRegistration::create([
            'student_id' => $this->student->id,
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
        AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'credit_hours' => 3.0,
            'credit_points' => 3.0,
            'enrollment_date' => now()->toDateString(),
        ]);
    };
});

// ─── Default weighted-percentage (null scheme) ───────────────────────────────

test('default: aggregateManualGrades computes weighted average unchanged', function () {
    $syllabus = ($this->makeSyllabus)(null);
    $offering = ($this->makeOffering)($syllabus);
    ($this->enrollStudent)($offering);

    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'weight' => 100,
        'code' => 'EXAM',
    ]);
    $detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'weight' => 1,
    ]);
    AssessmentComponentDetailScore::create([
        'student_id' => $this->student->id,
        'course_offering_id' => $offering->id,
        'assessment_component_detail_id' => $detail->id,
        'percentage_score' => 75.0,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);

    $this->service->aggregateManualGrades($offering);

    $record = AcademicRecord::where('student_id', $this->student->id)
        ->where('course_offering_id', $offering->id)
        ->first();

    expect((float) $record->final_percentage)->toBe(75.0)
        ->and($record->grade_breakdown['engine'])->toBe('default_weighted_percentage');
});

// ─── metropolia_v1 scheme ────────────────────────────────────────────────────

test('metropolia_v1: exam 88% → final_grade 5, grade_breakdown stored', function () {
    $scheme = [
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
    $syllabus = ($this->makeSyllabus)($scheme);
    $offering = ($this->makeOffering)($syllabus);
    ($this->enrollStudent)($offering);

    foreach (['ASSIGNMENT' => 55.0, 'EXAM' => 88.0] as $code => $pct) {
        $component = AssessmentComponent::factory()->create([
            'syllabus_template_id' => $syllabus->id,
            'weight' => $code === 'EXAM' ? 100 : 0,
            'code' => $code,
        ]);
        $detail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 1,
        ]);
        AssessmentComponentDetailScore::create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'assessment_component_detail_id' => $detail->id,
            'percentage_score' => $pct,
            'score_status' => 'final',
            'score_excluded' => false,
        ]);
    }

    $this->service->aggregateManualGrades($offering);

    $record = AcademicRecord::where('student_id', $this->student->id)
        ->where('course_offering_id', $offering->id)
        ->first();

    expect($record->final_letter_grade)->toBe('5')
        ->and($record->grade_breakdown['engine'])->toBe('metropolia_v1')
        ->and($record->grade_breakdown['final_grade'])->toBe('5');
});

test('metropolia_v1: gate failure stored in grade_breakdown', function () {
    $scheme = [
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
    $syllabus = ($this->makeSyllabus)($scheme);
    $offering = ($this->makeOffering)($syllabus);
    ($this->enrollStudent)($offering);

    foreach (['ASSIGNMENT' => 30.0, 'EXAM' => 88.0] as $code => $pct) {
        $component = AssessmentComponent::factory()->create([
            'syllabus_template_id' => $syllabus->id,
            'weight' => $code === 'EXAM' ? 100 : 0,
            'code' => $code,
        ]);
        $detail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 1,
        ]);
        AssessmentComponentDetailScore::create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'assessment_component_detail_id' => $detail->id,
            'percentage_score' => $pct,
            'score_status' => 'final',
            'score_excluded' => false,
        ]);
    }

    $this->service->aggregateManualGrades($offering);

    $record = AcademicRecord::where('student_id', $this->student->id)
        ->where('course_offering_id', $offering->id)
        ->first();

    expect($record->grade_breakdown['gates_passed'])->toBeFalse()
        ->and($record->grade_breakdown['gate_failures'])->not->toBeEmpty();
});
