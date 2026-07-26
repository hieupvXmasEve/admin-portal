<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
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

const SYLLABUS_EDIT_LOCK_CSRF = 'syllabus-edit-lock-csrf-token';

beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create();
    $this->user = User::factory()->create();

    session([
        '_token' => SYLLABUS_EDIT_LOCK_CSRF,
        'current_campus_id' => $this->campus->id,
    ]);
    $this->app->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_syllabus', 'edit_syllabus']);

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function makeEditableTemplate(object $context): SyllabusTemplate
{
    return SyllabusTemplate::query()->create([
        'unit_id' => $context->unit->id,
        'title' => 'Editable Template',
        'version' => '1.0',
        'min_attendance_threshold' => 80,
        'min_grade_threshold' => 40,
        'exam_resit_fee' => 750000,
        'is_active' => true,
    ]);
}

function lockTemplateWithCompletedSession(object $context, SyllabusTemplate $template): void
{
    $offering = CourseOffering::factory()->create([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'syllabus_template_id' => $template->id,
        'campus_id' => $context->campus->id,
    ]);

    ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
    ]);
}

it('allows editing when no assigned offering has completed sessions', function () {
    $template = makeEditableTemplate($this);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.show', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Syllabus/TemplatesShow')
            ->where('can_edit', true)
            ->where('template.min_attendance_threshold', fn ($value) => (float) $value === 80.0)
            ->where('template.min_grade_threshold', fn ($value) => (float) $value === 40.0)
            ->where('template.exam_resit_fee', fn ($value) => (float) $value === 750000.0));

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.edit', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Syllabus/TemplatesEdit')
            ->where('can_edit', true));
});

it('locks editing when assigned to an offering with a completed class session', function () {
    $template = makeEditableTemplate($this);
    lockTemplateWithCompletedSession($this, $template);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.show', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Syllabus/TemplatesShow')
            ->where('can_edit', false));

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.edit', $template))
        ->assertRedirect(route('syllabus_templates.show', $template));
});

it('rejects updates for locked syllabus templates', function () {
    $template = makeEditableTemplate($this);
    lockTemplateWithCompletedSession($this, $template);

    actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SYLLABUS_EDIT_LOCK_CSRF)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->put(route('syllabus_templates.update', $template), [
            'title' => 'Changed Title',
        ])
        ->assertForbidden();

    expect($template->fresh()->title)->toBe('Editable Template');
});

it('marks locked templates in the index list', function () {
    makeEditableTemplate($this);
    $locked = SyllabusTemplate::query()->create([
        'unit_id' => $this->unit->id,
        'title' => 'Locked Template',
        'version' => '1.0',
        'is_active' => true,
    ]);
    lockTemplateWithCompletedSession($this, $locked);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Syllabus/TemplatesIndex')
            ->has('items.data', 2)
            ->where('items.data', function ($rows) {
                $lockedFlags = collect($rows)->pluck('is_locked_for_editing')->all();

                return in_array(true, $lockedFlags, true) && in_array(false, $lockedFlags, true);
            }));
});

it('still allows editing when offering only has non-completed sessions', function () {
    $template = makeEditableTemplate($this);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'syllabus_template_id' => $template->id,
        'campus_id' => $this->campus->id,
    ]);

    ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'scheduled',
    ]);

    expect($template->isLockedForEditing())->toBeFalse();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('syllabus_templates.edit', $template))
        ->assertOk();
});
