<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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

    $this->grantPermissions = function (array $permissions): void {
        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);

        app()->forgetInstance(PermissionService::class);
        app()->singleton(PermissionService::class, fn () => $permissionService);
    };

    ($this->grantPermissions)([
        'view_course_offering',
        'complete_course_offering',
    ]);
});

function makeOperationalOffering(object $context, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'not_started',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ], $overrides));
}

function makeOperationalSession(CourseOffering $courseOffering, array $overrides = []): ClassSession
{
    return ClassSession::factory()->create(array_merge([
        'course_offering_id' => $courseOffering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ], $overrides));
}

function recordOperationalAttendance(object $context, ClassSession $session, string $recordingMethod): Attendance
{
    $student = Student::factory()->forCampus($context->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
    ]);

    return Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
        'recording_method' => $recordingMethod,
    ]);
}

function mapOperationalCanvasCourse(CourseOffering $courseOffering, string $syncStatus): CanvasCourseMapping
{
    $integration = CanvasIntegration::query()->create([
        'canvas_url' => 'https://canvas.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    return CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $integration->id,
        'canvas_course_id' => 'canvas-'.$courseOffering->id,
        'canvas_course_name' => 'Canvas Course '.$courseOffering->id,
        'course_offering_id' => $courseOffering->id,
        'sync_status' => $syncStatus,
    ]);
}

function getOperationalState(object $context, CourseOffering $courseOffering): TestResponse
{
    return actingAs($context->user)
        ->withSession(['current_campus_id' => $context->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $courseOffering));
}

it('returns setup lifecycle with no blockers and an allowed finalize action for a fresh offering', function () {
    $offering = makeOperationalOffering($this);

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('course-offerings/Show')
            ->where('operational_state.lifecycle_stage', 'setup')
            ->where('operational_state.readiness_blockers', [])
            ->has('operational_state.available_actions', 1)
            ->where('operational_state.available_actions.0.action', 'finalize')
            ->where('operational_state.available_actions.0.allowed', true)
            ->where('operational_state.available_actions.0.blocked_by', []));
});

it('reports sessions without recorded attendance as a readiness blocker', function () {
    $offering = makeOperationalOffering($this);
    $first = makeOperationalSession($offering, ['session_title' => 'Week 1']);
    $second = makeOperationalSession($offering, ['session_title' => 'Week 2']);
    // Cancelled sessions never block completion.
    makeOperationalSession($offering, ['session_title' => 'Cancelled week', 'status' => 'cancelled']);

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.lifecycle_stage', 'grading')
            ->has('operational_state.readiness_blockers', 1)
            ->where('operational_state.readiness_blockers.0.code', 'sessions_missing_attendance')
            ->has('operational_state.readiness_blockers.0.message')
            ->has('operational_state.readiness_blockers.0.references', 2)
            ->where('operational_state.readiness_blockers.0.references.0.type', 'class_session')
            ->where('operational_state.readiness_blockers.0.references.0.id', $first->id)
            ->where('operational_state.readiness_blockers.0.references.1.id', $second->id)
            ->where('operational_state.available_actions.0.action', 'finalize')
            ->where('operational_state.available_actions.0.allowed', false)
            ->where('operational_state.available_actions.0.blocked_by', ['sessions_missing_attendance']));
});

it('reports sessions with only auto-system attendance as a readiness blocker', function () {
    $offering = makeOperationalOffering($this);
    $manual = makeOperationalSession($offering, ['session_title' => 'Manually confirmed']);
    recordOperationalAttendance($this, $manual, 'manual');
    $autoOnly = makeOperationalSession($offering, ['session_title' => 'Auto only']);
    recordOperationalAttendance($this, $autoOnly, 'auto_system');

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('operational_state.readiness_blockers', 1)
            ->where('operational_state.readiness_blockers.0.code', 'sessions_auto_attendance_only')
            ->has('operational_state.readiness_blockers.0.references', 1)
            ->where('operational_state.readiness_blockers.0.references.0.type', 'class_session')
            ->where('operational_state.readiness_blockers.0.references.0.id', $autoOnly->id)
            ->where('operational_state.available_actions.0.allowed', false)
            ->where('operational_state.available_actions.0.blocked_by', ['sessions_auto_attendance_only']));
});

it('reports a mapped-but-unsynced Canvas offering as a readiness blocker', function () {
    $offering = makeOperationalOffering($this, ['is_canvas_synced' => false]);
    $mapping = mapOperationalCanvasCourse($offering, 'mapped');

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('operational_state.readiness_blockers', 1)
            ->where('operational_state.readiness_blockers.0.code', 'canvas_unsynced')
            ->where('operational_state.readiness_blockers.0.references.0.type', 'canvas_course_mapping')
            ->where('operational_state.readiness_blockers.0.references.0.id', $mapping->id)
            ->where('operational_state.available_actions.0.allowed', false)
            ->where('operational_state.available_actions.0.blocked_by', ['canvas_unsynced']));
});

it('does not report a Canvas blocker for non-blocking mapping states', function (?string $syncStatus, bool $isCanvasSynced) {
    $offering = makeOperationalOffering($this, ['is_canvas_synced' => $isCanvasSynced]);
    if ($syncStatus !== null) {
        mapOperationalCanvasCourse($offering, $syncStatus);
    }

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.readiness_blockers', [])
            ->where('operational_state.available_actions.0.allowed', true));
})->with([
    'pending mapping' => ['pending', false],
    'ignored mapping' => ['ignored', false],
    'mapped and synced' => ['mapped', true],
    'no mapping' => [null, false],
]);

it('exposes per-session attendance status matching the readiness blockers', function () {
    $offering = makeOperationalOffering($this);
    // Explicit sequence_number avoids a unique-constraint collision on the
    // factory's random default when several sessions share one course
    // offering; ordering below relies on the distinct session_date values.
    $notRecorded = makeOperationalSession($offering, ['session_title' => 'Not recorded', 'session_date' => '2026-01-10', 'sequence_number' => 1]);
    $autoOnly = makeOperationalSession($offering, ['session_title' => 'Auto only', 'session_date' => '2026-01-15', 'sequence_number' => 2]);
    recordOperationalAttendance($this, $autoOnly, 'auto_system');
    $recorded = makeOperationalSession($offering, ['session_title' => 'Recorded', 'session_date' => '2026-01-20', 'sequence_number' => 3]);
    recordOperationalAttendance($this, $recorded, 'manual');

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('operational_state.session_attendance_status', 3)
            ->where('operational_state.session_attendance_status.0.session_id', $notRecorded->id)
            ->where('operational_state.session_attendance_status.0.status', 'not_recorded')
            ->where('operational_state.session_attendance_status.1.session_id', $autoOnly->id)
            ->where('operational_state.session_attendance_status.1.status', 'auto_system_only')
            ->where('operational_state.session_attendance_status.2.session_id', $recorded->id)
            ->where('operational_state.session_attendance_status.2.status', 'recorded'));
});

it('returns no blockers and an allowed finalize for a fully ready offering', function () {
    $offering = makeOperationalOffering($this, ['is_canvas_synced' => true]);
    mapOperationalCanvasCourse($offering, 'mapped');
    $session = makeOperationalSession($offering);
    recordOperationalAttendance($this, $session, 'manual');

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.lifecycle_stage', 'grading')
            ->where('operational_state.readiness_blockers', [])
            ->where('operational_state.available_actions.0.action', 'finalize')
            ->where('operational_state.available_actions.0.allowed', true)
            ->where('operational_state.available_actions.0.blocked_by', []));
});

it('derives teaching and registration lifecycle stages from session state', function () {
    $teaching = makeOperationalOffering($this);
    makeOperationalSession($teaching, ['status' => 'in_progress']);
    makeOperationalSession($teaching, ['status' => 'scheduled']);

    getOperationalState($this, $teaching)
        ->assertInertia(fn ($page) => $page->where('operational_state.lifecycle_stage', 'teaching'));

    $registration = makeOperationalOffering($this);
    makeOperationalSession($registration, ['status' => 'scheduled']);

    getOperationalState($this, $registration)
        ->assertInertia(fn ($page) => $page->where('operational_state.lifecycle_stage', 'registration'));
});

it('reports terminal lifecycle stages without blockers or a finalize action', function (string $courseStatus) {
    $offering = makeOperationalOffering($this, ['course_status' => $courseStatus]);
    makeOperationalSession($offering);

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.lifecycle_stage', $courseStatus)
            ->where('operational_state.readiness_blockers', [])
            ->where('operational_state.available_actions', []));
})->with(['completed', 'cancelled']);

it('omits the finalize action for users lacking complete_course_offering', function () {
    ($this->grantPermissions)(['view_course_offering']);
    $offering = makeOperationalOffering($this);

    getOperationalState($this, $offering)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.available_actions', []));
});
