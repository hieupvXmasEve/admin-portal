<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Http\Middleware\HandleInertiaRequests;
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
use App\Models\User;
use App\Services\CourseCompletionService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Cockpit Scores tab scheme display (metropolia-grading-display issue 02).
 * All scheme fields flow through GradeDisplayPresenter and the stored
 * grade_breakdown — no live recomputation (ADR 0014).
 */
beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn([
        'view_course_offering',
        'view_attendance',
    ]);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

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

    $this->getScoresProps = function (CourseOffering $offering) {
        return actingAs($this->user)
            ->withSession(['current_campus_id' => $this->campus->id])
            ->get(route(CourseOfferingRoutes::SHOW, $offering), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'CourseOfferings/Show',
                'X-Inertia-Partial-Data' => 'scoresData',
            ]);
    };
});

it('shows scheme fields for a finalized scheme offering: converted grades, requirement status, final grade, pass status', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering, ['is_passed' => true]);

    ($this->scoreComponent)($offering, $syllabus, $student, 'ASSIGNMENT', 0, 55.0);
    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 88.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getScoresProps)($offering);

    $response->assertOk()
        ->assertJsonPath('props.scoresData.course_offering.scheme.engine', 'metropolia_v1')
        ->assertJsonPath('props.scoresData.course_offering.scheme.scale', 'numeric_0_5')
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display.scheme_engine', 'metropolia_v1')
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display.final_label', '5')
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display.pass_status', 'passed');

    $components = collect($response->json('props.scoresData.scores_grid.0.grade_display.components'))->keyBy('code');
    expect($components['ASSIGNMENT']['requirement_status'])->toBe('passed')
        ->and((float) $components['EXAM']['converted_grade'])->toBe(5.0)
        ->and($components['EXAM']['requirement_status'])->toBe('passed');
});

it('shows requirement "not met" on the gating component with a failed final grade for a gate-fail student', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering, ['is_passed' => false]);

    // Fails the ASSIGNMENT gate (< 40%) despite a high EXAM score.
    ($this->scoreComponent)($offering, $syllabus, $student, 'ASSIGNMENT', 0, 30.0);
    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 88.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getScoresProps)($offering);

    $components = collect($response->json('props.scoresData.scores_grid.0.grade_display.components'))->keyBy('code');

    expect($components['ASSIGNMENT']['requirement_status'])->toBe('failed')
        ->and($response->json('props.scoresData.scores_grid.0.grade_display.pass_status'))->toBe('failed');
});

it('renders no requirement indicator for components without a passing requirement', function () {
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

    $response = ($this->getScoresProps)($offering);

    $components = collect($response->json('props.scoresData.scores_grid.0.grade_display.components'))->keyBy('code');
    expect($components['EXAM']['requirement_status'])->toBeNull();
});

it('shows the scheme badge on the tab header before finalization', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'ASSIGNMENT', 0, 55.0);
    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 88.0);

    // Not finalized — no aggregateManualGrades call, no grade_breakdown stored.
    $response = ($this->getScoresProps)($offering);

    $response->assertOk()
        ->assertJsonPath('props.scoresData.course_offering.scheme.engine', 'metropolia_v1')
        ->assertJsonPath('props.scoresData.course_offering.scheme.scale', 'numeric_0_5')
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display', null);
});

it('keeps the raw grid unchanged with an empty scheme grade for an unfinalized scheme offering', function () {
    $syllabus = ($this->makeSyllabus)($this->gateFailScheme);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 82.5);

    $response = ($this->getScoresProps)($offering);

    $response->assertOk()
        ->assertJsonPath('props.scoresData.scores_grid.0.component_totals.0.percentage_score', 82.5)
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display', null)
        ->assertJsonPath('props.scoresData.course_offering.scheme.engine', 'metropolia_v1');
});

it('renders default-weighted offerings unchanged: no scheme fields, no grade_display', function () {
    $syllabus = ($this->makeSyllabus)(null);
    $offering = ($this->makeOffering)($syllabus);
    $student = ($this->enrollStudent)($offering);

    ($this->scoreComponent)($offering, $syllabus, $student, 'EXAM', 100, 75.0);

    app(CourseCompletionService::class)->aggregateManualGrades($offering);

    $response = ($this->getScoresProps)($offering);

    $response->assertOk()
        ->assertJsonPath('props.scoresData.course_offering.scheme', null)
        ->assertJsonPath('props.scoresData.scores_grid.0.grade_display', null)
        ->assertJsonPath('props.scoresData.scores_grid.0.total_percentage', '75.00');
});
