<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use App\Models\Module;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for Module CRUD and unit-syncing before they move
 * off the frozen routes/web/modules.php split file into owned Academic
 * Catalog (zero-migration-debt-closure phase 4).
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
    actingAs(User::factory()->create());
});

it('lists modules with search, campus filter, and campus DTO shape', function (): void {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    Module::factory()->create(['campus_id' => $campusA->id, 'code' => 'IT-100', 'name' => 'Intro to Computing']);
    Module::factory()->create(['campus_id' => $campusB->id, 'code' => 'BUS-100', 'name' => 'Intro to Business']);

    $this->get(route('admin.modules.index', ['search' => 'Computing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Modules/Index')
            ->where('modules.data.0.code', 'IT-100')
            ->where('modules.total', 1)
            ->has('campuses.0', fn ($c) => $c->hasAll(['id', 'name', 'code']))
        );

    $this->get(route('admin.modules.index', ['campus_id' => $campusB->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modules.total', 1)
            ->where('modules.data.0.code', 'BUS-100')
        );
});

it('sorts modules by the requested column and direction', function (): void {
    $campus = Campus::first();
    Module::factory()->create(['campus_id' => $campus->id, 'code' => 'AAA-100', 'total_credits' => 3]);
    Module::factory()->create(['campus_id' => $campus->id, 'code' => 'ZZZ-100', 'total_credits' => 9]);

    $this->get(route('admin.modules.index', ['sort' => 'total_credits', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modules.data.0.code', 'ZZZ-100')
            ->where('filters.sort', 'total_credits')
            ->where('filters.direction', 'desc')
        );
});

it('rejects an invalid sort field on the module index', function (): void {
    $this->get(route('admin.modules.index', ['sort' => 'not_a_column']))
        ->assertSessionHasErrors('sort');
});

it('renders the create form with campuses, modules, and units', function (): void {
    $campus = Campus::first();
    Module::factory()->create(['campus_id' => $campus->id]);
    Unit::factory()->create();

    $this->get(route('admin.modules.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Modules/Create')
            ->has('campuses', 1)
            ->has('campuses.0', fn ($c) => $c->hasAll(['id', 'name', 'code']))
            ->has('modules', 1)
            ->has('units', 1)
        );
});

it('creates a module with units and redirects to its show page', function (): void {
    $campus = Campus::factory()->create();
    $unit = Unit::factory()->create();

    $this->post(route('admin.modules.store'), [
        'campus_id' => $campus->id,
        'code' => 'IT-200',
        'name' => 'Data Structures',
        'grading_type' => 'grade',
        'units' => [
            ['unit_id' => $unit->id, 'weight' => 1.5, 'order' => 0],
        ],
    ])->assertRedirect()
        ->assertSessionHas('success');

    $module = Module::where('code', 'IT-200')->firstOrFail();
    expect($module->units)->toHaveCount(1);
    expect((float) $module->total_credits)->toBe((float) $unit->credit_points);
});

it('rejects module creation with a duplicate code on the same campus', function (): void {
    $campus = Campus::factory()->create();
    Module::factory()->create(['campus_id' => $campus->id, 'code' => 'DUP-1']);

    $this->post(route('admin.modules.store'), [
        'campus_id' => $campus->id,
        'code' => 'DUP-1',
        'name' => 'Duplicate',
        'grading_type' => 'grade',
    ])->assertSessionHasErrors('code');
});

it('shows a module with its curriculum usage and non-egc available units', function (): void {
    $module = Module::factory()->create();
    $program = Program::factory()->create();
    $version = CurriculumVersion::factory()->create(['program_id' => $program->id]);
    CurriculumModule::create(['curriculum_version_id' => $version->id, 'module_id' => $module->id]);
    Unit::factory()->create(['unit_type' => 'egc']);
    $availableUnit = Unit::factory()->create();

    $this->get(route('admin.modules.show', $module))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Modules/Show')
            ->where('module.id', $module->id)
            ->where('module.curriculum_modules.0.curriculum_version.program.id', $program->id)
            ->has('availableUnits', 1)
            ->where('availableUnits.0.id', $availableUnit->id)
        );
});

it('renders the edit form for a module', function (): void {
    $module = Module::factory()->create();

    $this->get(route('admin.modules.edit', $module))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Modules/Edit')
            ->where('module.id', $module->id)
        );
});

it('excludes itself from the prerequisite candidate list on the edit form', function (): void {
    $module = Module::factory()->create();
    $other = Module::factory()->create();

    $this->get(route('admin.modules.edit', $module))
        ->assertInertia(fn ($page) => $page
            ->has('modules', 1)
            ->where('modules.0.id', $other->id)
        );
});

it('updates a module and redirects to its show page', function (): void {
    $module = Module::factory()->create();

    $this->put(route('admin.modules.update', $module), [
        'name' => 'Renamed Module',
    ])->assertRedirect(route('admin.modules.show', $module->id))
        ->assertSessionHas('success');

    expect($module->fresh()->name)->toBe('Renamed Module');
});

it('deletes a module with no curriculum usage', function (): void {
    $module = Module::factory()->create();

    $this->delete(route('admin.modules.destroy', $module))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('modules', ['id' => $module->id]);
});

it('blocks deleting a module assigned to a curriculum version', function (): void {
    $module = Module::factory()->create();
    $program = Program::factory()->create();
    $version = CurriculumVersion::factory()->create(['program_id' => $program->id]);
    CurriculumModule::create(['curriculum_version_id' => $version->id, 'module_id' => $module->id]);

    $this->delete(route('admin.modules.destroy', $module))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('modules', ['id' => $module->id, 'deleted_at' => null]);
});

it('syncs module units and recalculates total credits', function (): void {
    $module = Module::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 6.0]);

    $this->post(route('admin.modules.sync-units', $module), [
        'units' => [
            ['unit_id' => $unit->id, 'grading_type' => 'grade', 'weight' => 1, 'order' => 0],
        ],
    ])->assertRedirect()
        ->assertSessionHas('success');

    $module->refresh();
    expect($module->units)->toHaveCount(1);
    expect((float) $module->total_credits)->toBe(6.0);
});
