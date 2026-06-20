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
use App\Services\PermissionService;
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

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_canvas_integration', 'map_canvas_courses']);

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);

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