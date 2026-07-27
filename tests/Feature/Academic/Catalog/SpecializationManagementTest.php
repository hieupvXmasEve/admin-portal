<?php

declare(strict_types=1);

use App\Constants\ProgramRoutes;
use App\Constants\SpecializationRoutes;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for Specialization CRUD, bulk-delete, and the
 * curriculum-version update action before they move off the frozen
 * routes/web/specializations.php split file into owned Academic Catalog
 * (zero-migration-debt-closure phase 4).
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

it('lists specializations with search, program filter, sort, and statistics', function (): void {
    $programA = Program::factory()->create(['name' => 'BSc Computing']);
    $programB = Program::factory()->create(['name' => 'BSc Business']);
    Specialization::factory()->forProgram($programA)->create(['name' => 'Cybersecurity', 'code' => 'IT-CS', 'is_active' => true]);
    Specialization::factory()->forProgram($programB)->create(['name' => 'Marketing', 'code' => 'BUS-MKT', 'is_active' => false]);

    $this->get(route(SpecializationRoutes::INDEX, ['search' => 'Cyber']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Specializations/Index')
            ->where('specializations.data.0.code', 'IT-CS')
            ->where('specializations.total', 1)
            ->where('statistics.total_specializations', 2)
            ->where('statistics.active_specializations', 1)
            ->where('statistics.inactive_specializations', 1)
        );

    $this->get(route(SpecializationRoutes::INDEX, ['program_id' => $programB->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('specializations.total', 1)
            ->where('specializations.data.0.code', 'BUS-MKT')
        );
});

it('rejects an invalid sort field on the specialization index', function (): void {
    $this->get(route(SpecializationRoutes::INDEX, ['sort' => 'not_a_column']))
        ->assertSessionHasErrors('sort');
});

it('renders the create form with the program list', function (): void {
    Program::factory()->create(['name' => 'BSc Computing']);

    $this->get(route(SpecializationRoutes::CREATE))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Specializations/Create')
            ->has('programs', 1)
        );
});

it('creates a specialization and redirects to the program show page when source=program-show', function (): void {
    $program = Program::factory()->create();

    $this->post(route(SpecializationRoutes::STORE, ['source' => 'program-show']), [
        'program_id' => $program->id,
        'name' => 'Data Science',
        'code' => 'IT-DS',
    ])
        ->assertRedirect(route(ProgramRoutes::SHOW, ['program' => $program->id]))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('specializations', ['code' => 'IT-DS', 'is_active' => true]);
});

it('creates a specialization and redirects to the index scoped to its program by default', function (): void {
    $program = Program::factory()->create();

    $this->post(route(SpecializationRoutes::STORE), [
        'program_id' => $program->id,
        'name' => 'Data Science',
        'code' => 'IT-DS2',
    ])->assertRedirect(route(SpecializationRoutes::INDEX, ['program_id' => $program->id]));
});

it('rejects specialization creation with a duplicate code', function (): void {
    $program = Program::factory()->create();
    Specialization::factory()->forProgram($program)->create(['code' => 'DUP-1']);

    $this->post(route(SpecializationRoutes::STORE), [
        'program_id' => $program->id,
        'name' => 'Duplicate',
        'code' => 'DUP-1',
    ])->assertSessionHasErrors('code');
});

it('shows a specialization with curriculum version statistics', function (): void {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();
    CurriculumVersion::factory()->create(['specialization_id' => $specialization->id, 'program_id' => $program->id]);

    $this->get(route(SpecializationRoutes::SHOW, $specialization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Specializations/Show')
            ->where('specialization.id', $specialization->id)
            ->where('statistics.curriculum_versions_count', 1)
            ->where('statistics.specialization_level_versions', 1)
        );
});

it('renders the edit form for a specialization', function (): void {
    $specialization = Specialization::factory()->create();

    $this->get(route(SpecializationRoutes::EDIT, $specialization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Specializations/Edit')
            ->where('specialization.id', $specialization->id)
        );
});

it('updates a specialization and redirects to the program show page when source=program-show', function (): void {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();

    $this->put(route(SpecializationRoutes::UPDATE, [$specialization, 'source' => 'program-show']), [
        'program_id' => $program->id,
        'name' => 'Renamed via program',
        'code' => $specialization->code,
    ])->assertRedirect(route(ProgramRoutes::SHOW, ['program' => $program->id]));

    expect($specialization->fresh()->name)->toBe('Renamed via program');
});

it('updates a specialization and redirects back to the index', function (): void {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();

    $this->put(route(SpecializationRoutes::UPDATE, $specialization), [
        'program_id' => $program->id,
        'name' => 'Renamed',
        'code' => $specialization->code,
    ])->assertRedirect(route(SpecializationRoutes::INDEX, ['program_id' => $program->id]));

    expect($specialization->fresh()->name)->toBe('Renamed');
});

it('deletes a specialization', function (): void {
    $specialization = Specialization::factory()->create();

    $this->delete(route(SpecializationRoutes::DESTROY, $specialization))
        ->assertRedirect(route(SpecializationRoutes::INDEX));

    $this->assertDatabaseMissing('specializations', ['id' => $specialization->id]);
});

it('deletes a specialization through the API with the raw success envelope', function (): void {
    $specialization = Specialization::factory()->create();

    $this->deleteJson(route(SpecializationRoutes::API_DESTROY, $specialization))
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('specializations', ['id' => $specialization->id]);
});

it('bulk deletes specializations with the raw deleted/failed envelope', function (): void {
    $ids = Specialization::factory()->count(2)->create()->pluck('id')->all();

    $this->deleteJson(route(SpecializationRoutes::API_BULK_DELETE), ['specialization_ids' => $ids])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'deleted');
});

it('keeps the bulk-delete route behind the delete_specialization permission', function (): void {
    $route = app('router')->getRoutes()->getByName(SpecializationRoutes::API_BULK_DELETE);

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('can:delete_specialization');
});

it('updates a curriculum version through the specialization API action', function (): void {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();
    $version = CurriculumVersion::factory()->create([
        'program_id' => $program->id,
        'specialization_id' => $specialization->id,
    ]);

    $semester = Semester::factory()->create();

    $this->putJson(route(SpecializationRoutes::API_CURRICULUM_VERSION_UPDATE, $version), [
        'version_code' => 'NEW-CODE',
        'semester_id' => $semester->id,
        'notes' => null,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.version_code', 'NEW-CODE');

    expect($version->fresh()->version_code)->toBe('NEW-CODE');
});
