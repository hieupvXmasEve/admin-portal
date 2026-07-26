<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
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
    $this->unit = Unit::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn([
            'create_course',
            'edit_course',
            'create_course_offering',
            'edit_course_offering',
        ]);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->canvasIntegration = CanvasIntegration::query()->create([
        'canvas_url' => 'https://canvas.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);
});

function makeSyllabusTemplate(Unit $unit, string $title): SyllabusTemplate
{
    return SyllabusTemplate::query()->create([
        'unit_id' => $unit->id,
        'title' => $title,
        'version' => '1.0',
        'is_active' => true,
    ]);
}

function makeCourseOffering(object $context, SyllabusTemplate $template, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'syllabus_template_id' => $template->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'not_started',
        'enrollment_status' => 'open',
    ], $overrides));
}

function mapCanvasCourse(object $context, CourseOffering $courseOffering, string $canvasCourseId): CanvasCourseMapping
{
    return CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $context->canvasIntegration->id,
        'canvas_course_id' => $canvasCourseId,
        'canvas_course_name' => "Canvas Course {$canvasCourseId}",
        'course_offering_id' => $courseOffering->id,
        'sync_status' => 'mapped',
    ]);
}

function courseOfferingPayload(object $context, SyllabusTemplate $template, array $overrides = []): array
{
    return array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'syllabus_template_id' => $template->id,
        'section_code' => 'NEW',
        'max_capacity' => 30,
        'delivery_mode' => 'in_person',
    ], $overrides);
}

it('omits Canvas-reserved syllabus templates from the create page', function () {
    $reusable = makeSyllabusTemplate($this->unit, 'Reusable Template');
    makeSyllabusTemplate($this->unit, 'Canvas Manual Template');

    $mapped = makeSyllabusTemplate($this->unit, 'Mapped Before Assignment Sync');
    $mappedOffering = makeCourseOffering($this, $mapped, ['section_code' => 'MAP']);
    mapCanvasCourse($this, $mappedOffering, 'canvas-mapped');

    $synced = makeSyllabusTemplate($this->unit, 'Renamed After Sync');
    makeCourseOffering($this, $synced, [
        'section_code' => 'SYN',
        'is_canvas_synced' => true,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::CREATE))
        ->assertInertia(fn ($page) => $page
            ->component('CourseOfferings/Create')
            ->has('syllabus_templates', 1)
            ->where('syllabus_templates.0.id', $reusable->id));
});

it('rejects a mapped Canvas syllabus template when creating another offering', function () {
    $mapped = makeSyllabusTemplate($this->unit, 'Mapped Before Assignment Sync');
    $mappedOffering = makeCourseOffering($this, $mapped, ['section_code' => 'MAP']);
    mapCanvasCourse($this, $mappedOffering, 'canvas-mapped');

    actingAs($this->user)
        ->from(route(CourseOfferingRoutes::CREATE))
        ->withSession(['current_campus_id' => $this->campus->id])
        ->post(route(CourseOfferingRoutes::STORE), courseOfferingPayload($this, $mapped))
        ->assertSessionHasErrors(['syllabus_template_id']);

    expect(CourseOffering::query()->where('section_code', 'NEW')->exists())->toBeFalse();
});

it('keeps the current mapped syllabus visible on edit but omits another mapped syllabus', function () {
    $current = makeSyllabusTemplate($this->unit, 'Canvas Current Template');
    $currentOffering = makeCourseOffering($this, $current, ['section_code' => 'CUR']);
    mapCanvasCourse($this, $currentOffering, 'canvas-current');

    $other = makeSyllabusTemplate($this->unit, 'Other Mapped Template');
    $otherOffering = makeCourseOffering($this, $other, ['section_code' => 'OTH']);
    mapCanvasCourse($this, $otherOffering, 'canvas-other');

    $reusable = makeSyllabusTemplate($this->unit, 'Reusable Template');

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::EDIT, $currentOffering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CourseOfferings/Edit')
            ->has('syllabus_templates', 2)
            ->where('syllabus_templates.0.id', $current->id)
            ->where('syllabus_templates.1.id', $reusable->id));
});

it('rejects assigning another mapped Canvas syllabus during update', function () {
    $reusable = makeSyllabusTemplate($this->unit, 'Reusable Template');
    $targetOffering = makeCourseOffering($this, $reusable, ['section_code' => 'TGT']);

    $mapped = makeSyllabusTemplate($this->unit, 'Mapped Before Assignment Sync');
    $mappedOffering = makeCourseOffering($this, $mapped, ['section_code' => 'MAP']);
    mapCanvasCourse($this, $mappedOffering, 'canvas-mapped');

    actingAs($this->user)
        ->from(route(CourseOfferingRoutes::EDIT, $targetOffering))
        ->withSession(['current_campus_id' => $this->campus->id])
        ->put(
            route(CourseOfferingRoutes::UPDATE, $targetOffering),
            courseOfferingPayload($this, $mapped, ['section_code' => 'TGT'])
        )
        ->assertSessionHasErrors(['syllabus_template_id']);

    expect($targetOffering->fresh()->syllabus_template_id)->toBe($reusable->id);
});

it('allows a mapped offering to keep its current syllabus during update', function () {
    $current = makeSyllabusTemplate($this->unit, 'Canvas Current Template');
    $currentOffering = makeCourseOffering($this, $current, ['section_code' => 'CUR']);
    mapCanvasCourse($this, $currentOffering, 'canvas-current');

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->put(
            route(CourseOfferingRoutes::UPDATE, $currentOffering),
            courseOfferingPayload($this, $current, [
                'section_code' => 'CUR',
                'notes' => 'Updated without changing the reserved template.',
            ])
        )
        ->assertRedirectToRoute(CourseOfferingRoutes::INDEX);

    expect($currentOffering->fresh()->notes)->toBe('Updated without changing the reserved template.');
});

it('rejects duplicating an offering whose syllabus is reserved by Canvas mapping', function () {
    $mapped = makeSyllabusTemplate($this->unit, 'Mapped Before Assignment Sync');
    $mappedOffering = makeCourseOffering($this, $mapped, ['section_code' => 'SRC']);
    mapCanvasCourse($this, $mappedOffering, 'canvas-mapped');

    actingAs($this->user)
        ->from(route(CourseOfferingRoutes::INDEX))
        ->withSession(['current_campus_id' => $this->campus->id])
        ->post(route(CourseOfferingRoutes::DUPLICATE, $mappedOffering))
        ->assertSessionHas(
            'inertia.flash_data.error',
            'Cannot duplicate a Canvas-linked course offering. Create a new offering and select a reusable syllabus template.'
        );

    expect(CourseOffering::query()->count())->toBe(1);
});
