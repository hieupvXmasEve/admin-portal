<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasAssignmentSyncService;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasSyllabusService;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->otherSemester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create(['code' => 'ACC101', 'name' => 'Accounting Basics']);
    $this->otherUnit = Unit::factory()->create(['code' => 'MKT201', 'name' => 'Marketing']);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_canvas_integration', 'map_canvas_courses']);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->canvasIntegration = CanvasIntegration::query()->create([
        'canvas_url' => 'https://canvas.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'is_active' => true,
    ]);

    $this->template = SyllabusTemplate::query()->create([
        'unit_id' => $this->unit->id,
        'title' => 'Template',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $this->otherTemplate = SyllabusTemplate::query()->create([
        'unit_id' => $this->otherUnit->id,
        'title' => 'Other Template',
        'version' => '1.0',
        'is_active' => true,
    ]);
});

function makeOffering(object $context, Unit $unit, SyllabusTemplate $template, Semester $semester, string $sectionCode): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => $template->id,
        'campus_id' => $context->campus->id,
        'section_code' => $sectionCode,
        'course_status' => 'not_started',
        'enrollment_status' => 'open',
    ]);
}

it('ignores invalid sort values and still returns the canvas courses page', function () {
    CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => '1001',
        'canvas_course_code' => 'PENDING-1',
        'sync_status' => 'pending',
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('admin.canvas.courses.index', [
            'sync_status' => 'pending',
            'sort' => 'function sort() { [native code] }',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Canvas/Courses/Index')
            ->where('filters.sort', 'created_at')
            ->where('filters.sync_status', 'pending')
            ->has('mappings.data', 1));
});

it('filters mapped canvas courses by semester and unit', function () {
    $matchingOffering = makeOffering($this, $this->unit, $this->template, $this->semester, 'A1');
    $otherOffering = makeOffering($this, $this->otherUnit, $this->otherTemplate, $this->otherSemester, 'B2');

    CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => '2001',
        'canvas_course_code' => 'MATCH',
        'course_offering_id' => $matchingOffering->id,
        'sync_status' => 'mapped',
    ]);

    CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => '2002',
        'canvas_course_code' => 'OTHER',
        'course_offering_id' => $otherOffering->id,
        'sync_status' => 'mapped',
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('admin.canvas.courses.index', [
            'sync_status' => 'mapped',
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Canvas/Courses/Index')
            ->has('mappings.data', 1)
            ->where('mappings.data.0.canvas_course_code', 'MATCH'));
});

it('searches mapped offerings by unit code and section code', function () {
    $offering = makeOffering($this, $this->unit, $this->template, $this->semester, 'SEC-77');

    CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => '3001',
        'canvas_course_code' => 'CANVAS-X',
        'course_offering_id' => $offering->id,
        'sync_status' => 'mapped',
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('admin.canvas.courses.index', ['search' => 'SEC-77']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('mappings.data', 1));

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('admin.canvas.courses.index', ['search' => 'ACC101']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('mappings.data', 1));
});

it('returns campus-scoped available offerings in the canonical API envelope', function () {
    $offering = makeOffering($this, $this->unit, $this->template, $this->semester, 'API-1');
    $otherCampus = Campus::factory()->create();
    $otherCampusOffering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'section_code' => 'OTHER',
        'is_active' => true,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->getJson(route('admin.canvas.api.course-offerings', [
            'semester_id' => $this->semester->id,
            'search' => 'API-1',
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Course offerings retrieved successfully')
        ->assertJsonPath('data.0.id', $offering->id)
        ->assertJsonMissing(['id' => $otherCampusOffering->id]);
});

it('accepts the legacy all-semester sentinel for available offerings', function () {
    $offering = makeOffering($this, $this->unit, $this->template, $this->semester, 'ALL-1');

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->getJson(route('admin.canvas.api.course-offerings', ['semester_id' => 'all']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $offering->id);
});

it('uses the canonical API envelope for Canvas syllabus, assignment, and grade syncs', function () {
    $mapping = CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => 'SYNC-1',
        'sync_status' => 'mapped',
    ]);

    $syllabus = Mockery::mock(CanvasSyllabusService::class);
    $syllabus->shouldReceive('getSyncSummary')->once()->andReturn(['can_sync' => true, 'syllabus_title' => 'Template']);
    app()->instance(CanvasSyllabusService::class, $syllabus);

    $assignments = Mockery::mock(CanvasAssignmentSyncService::class);
    $assignments->shouldReceive('getDetailedSyncSummary')->once()->andReturn(['can_sync' => true, 'groups' => []]);
    app()->instance(CanvasAssignmentSyncService::class, $assignments);

    $grades = Mockery::mock(CanvasGradeSyncService::class);
    $grades->shouldReceive('getGradeSyncSummary')->once()->andReturn(['can_sync' => true, 'students' => []]);
    $grades->shouldReceive('syncCourseGrades')->once()->andReturn(['success' => true, 'students_synced' => 2]);
    app()->instance(CanvasGradeSyncService::class, $grades);

    actingAs($this->user)
        ->getJson(route('admin.canvas.courses.sync-summary', $mapping))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.syllabus_title', 'Template');

    actingAs($this->user)
        ->getJson(route('admin.canvas.courses.assignments.sync-summary', $mapping))
        ->assertOk()
        ->assertJsonPath('data.groups', []);

    actingAs($this->user)
        ->getJson(route('admin.canvas.courses.grades.sync-summary', $mapping))
        ->assertOk()
        ->assertJsonPath('data.students', []);

    actingAs($this->user)
        ->postJson(route('admin.canvas.courses.sync-grades', $mapping))
        ->assertOk()
        ->assertJsonPath('data.students_synced', 2);
});

it('validates selected Canvas assignment group identifiers', function () {
    $mapping = CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => 'SYNC-2',
        'sync_status' => 'mapped',
    ]);

    actingAs($this->user)
        ->postJson(route('admin.canvas.courses.sync-assignments', $mapping), ['selected_group_ids' => ['group-1', 2]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'selected_group_ids.1');
});

it('wraps Canvas syllabus summary failures in the canonical API envelope', function () {
    $mapping = CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $this->canvasIntegration->id,
        'canvas_course_id' => 'SYNC-FAILURE',
        'sync_status' => 'mapped',
    ]);

    $syllabus = Mockery::mock(CanvasSyllabusService::class);
    $syllabus->shouldReceive('getSyncSummary')->once()->andThrow(new RuntimeException('Canvas syllabus is unavailable'));
    app()->instance(CanvasSyllabusService::class, $syllabus);

    actingAs($this->user)
        ->getJson(route('admin.canvas.courses.sync-summary', $mapping))
        ->assertBadRequest()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.can_sync', false)
        ->assertJsonPath('message', 'Canvas syllabus is unavailable');
});

it('renders newest-first semester options and code-ordered unit options', function () {
    $archived = Semester::factory()->create([
        'code' => 'ARCHIVED',
        'is_archived' => true,
    ]);

    $response = actingAs($this->user)
        ->get(route('admin.canvas.courses.index'))
        ->assertOk();

    $props = $response->viewData('page')['props'];

    $semesterCodes = collect($props['semesters'])->pluck('code')->all();
    $semesterKeys = array_keys((array) collect($props['semesters'])->first());
    $unitCodes = collect($props['units'])->pluck('code')->all();
    $unitKeys = array_keys((array) collect($props['units'])->first());

    // Archived semesters stay out of the picker; both lists keep their
    // narrow column selection and their ordering.
    expect($semesterCodes)->not->toContain($archived->code)
        ->and($semesterKeys)->toBe(['id', 'name', 'code'])
        ->and($unitCodes)->toBe(['ACC101', 'MKT201'])
        ->and($unitKeys)->toBe(['id', 'code', 'name']);
});
