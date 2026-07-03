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
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;

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

function makeFinalizeOffering(object $context, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ], $overrides));
}

function makeFinalizeSession(CourseOffering $courseOffering, array $overrides = []): ClassSession
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

function recordFinalizeAttendance(object $context, ClassSession $session, string $recordingMethod): Attendance
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

function mapFinalizeCanvasCourse(CourseOffering $courseOffering, string $syncStatus): CanvasCourseMapping
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

/**
 * A "ready" offering: one completed session with manually confirmed
 * attendance and no registered students, so no attendance blocker and the
 * completion pipeline has nothing left to validate.
 */
function makeReadyFinalizeOffering(object $context, array $overrides = []): CourseOffering
{
    $offering = makeFinalizeOffering($context, $overrides);
    $session = makeFinalizeSession($offering);
    recordFinalizeAttendance($context, $session, 'manual');

    return $offering;
}

function postFinalize(object $context, CourseOffering $courseOffering): TestResponse
{
    return actingAs($context->user)
        ->withSession(['current_campus_id' => $context->campus->id])
        ->from(route(CourseOfferingRoutes::SHOW, $courseOffering))
        ->post(route(CourseOfferingRoutes::FINALIZE, $courseOffering));
}

it('returns 403 when the user lacks complete_course_offering', function () {
    ($this->grantPermissions)(['view_course_offering']);
    $offering = makeReadyFinalizeOffering($this);

    postFinalize($this, $offering)->assertForbidden();

    expect($offering->fresh()->course_status)->not->toBe('completed');
});

it('blocks finalization when sessions are missing attendance', function () {
    $offering = makeFinalizeOffering($this);
    makeFinalizeSession($offering, ['session_title' => 'Week 1']);

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->not->toBe('completed');
});

it('blocks finalization when sessions only have auto-system attendance', function () {
    $offering = makeFinalizeOffering($this);
    $session = makeFinalizeSession($offering, ['session_title' => 'Auto only']);
    recordFinalizeAttendance($this, $session, 'auto_system');

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->not->toBe('completed');
});

it('blocks finalization when a Canvas-mapped offering is not synced', function () {
    $offering = makeReadyFinalizeOffering($this, ['is_canvas_synced' => false]);
    mapFinalizeCanvasCourse($offering, 'mapped');

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->not->toBe('completed');

    // Flash rides outside props, so the blocker message must still surface on
    // the cockpit's partial reload of operational_state.
    $partialReload = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'course-offerings/Show',
            'X-Inertia-Partial-Data' => 'operational_state',
        ]);

    $partialReload->assertOk();
    expect($partialReload->json('flash.error'))->toContain('Canvas');
});

it('rejects a mapped-but-unsynced offering at the action level with a Canvas message', function () {
    $offering = makeReadyFinalizeOffering($this, ['is_canvas_synced' => false]);
    mapFinalizeCanvasCourse($offering, 'mapped');

    expect(fn () => MarkCourseOfferingCompletedAction::run($offering))
        ->toThrow(RuntimeException::class, 'Canvas');

    expect($offering->fresh()->course_status)->not->toBe('completed');
});

it('finalizes normally for non-blocking Canvas states', function (?string $syncStatus, bool $isCanvasSynced) {
    $offering = makeReadyFinalizeOffering($this, ['is_canvas_synced' => $isCanvasSynced]);
    if ($syncStatus !== null) {
        mapFinalizeCanvasCourse($offering, $syncStatus);
    }

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->toBe('completed');
})->with([
    'pending mapping' => ['pending', false],
    'ignored mapping' => ['ignored', false],
    'mapped and synced' => ['mapped', true],
    'no mapping' => [null, false],
]);

it('finalizes a ready offering and serves refreshed operational_state on a partial reload', function () {
    $offering = makeReadyFinalizeOffering($this);

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->toBe('completed');

    // The cockpit refreshes via an Inertia partial reload of only
    // operational_state; the contract must be served on partial requests.
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'course-offerings/Show',
            'X-Inertia-Partial-Data' => 'operational_state',
        ])
        ->assertOk()
        ->assertJsonPath('component', 'course-offerings/Show')
        ->assertJsonPath('props.operational_state.lifecycle_stage', 'completed')
        ->assertJsonPath('props.operational_state.readiness_blockers', [])
        ->assertJsonPath('props.operational_state.available_actions', [])
        ->assertJsonMissingPath('props.courseOffering');
});

it('does not re-finalize an already completed offering', function () {
    $offering = makeReadyFinalizeOffering($this, ['course_status' => 'completed']);

    postFinalize($this, $offering)
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect($offering->fresh()->course_status)->toBe('completed');
});

it('does not retroactively apply the Canvas rule when recalculating a completed offering', function () {
    $offering = makeReadyFinalizeOffering($this, ['course_status' => 'completed']);
    mapFinalizeCanvasCourse($offering, 'mapped');

    // Recalculation of an already-completed offering must not hit the new
    // Canvas block (forward-only rule, no backfill).
    $result = MarkCourseOfferingCompletedAction::run($offering, recalculate: true);

    expect($result['success'])->toBeTrue()
        ->and($offering->fresh()->course_status)->toBe('completed');
});
