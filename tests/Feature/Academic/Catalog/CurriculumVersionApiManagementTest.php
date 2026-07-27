<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumUnit;
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

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    actingAs($this->user);
});

it('returns catalog-owned curriculum lookups through the API envelope', function (): void {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();
    $version = CurriculumVersion::factory()->forProgram($program)->create(['version_code' => 'CAT-2027']);

    $this->getJson(route('api.curriculum_versions.specializations-by-program', ['program_id' => $program->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $specialization->id);

    $this->getJson(route('api.curriculum_versions.by-program-specialization', ['program_id' => $program->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $version->id);
});

it('creates and removes empty curriculum versions through Catalog API actions', function (): void {
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();

    $created = $this->postJson(route('api.curriculum_versions.store'), [
        'program_id' => $program->id,
        'version_code' => 'CAT-2028',
        'semester_id' => $semester->id,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.version_code', 'CAT-2028');

    $curriculumVersionId = $created->json('data.id');

    $this->deleteJson(route('api.curriculum_versions.destroy', $curriculumVersionId))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CurriculumVersion::query()->find($curriculumVersionId))->toBeNull();
});

it('bulk deletes empty curriculum versions with the established result details', function (): void {
    $version = CurriculumVersion::factory()->create(['version_code' => 'CAT-BULK']);

    $this->deleteJson(route('api.curriculum_versions.bulk-delete'), [
        'curriculum_version_ids' => [$version->id],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted.0', 'CAT-BULK')
        ->assertJsonPath('data.failed', []);

    expect(CurriculumVersion::query()->find($version->id))->toBeNull();
});

it('renders the Catalog-owned curriculum version create page', function (): void {
    $this->withoutMiddleware(Authorize::class);
    Program::factory()->create(['code' => 'CAT']);

    $this->get(route('curriculum_versions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CurriculumVersions/Create')
            ->has('programs', 1)
            ->has('specializations')
            ->has('semesters'));
});

it('sanitizes curriculum version list filters through the Catalog request contract', function (): void {
    $this->withoutMiddleware(Authorize::class);
    $program = Program::factory()->create(['name' => 'Catalog Program']);
    $version = CurriculumVersion::factory()->forProgram($program)->create(['version_code' => 'CAT-FILTER']);

    $this->get(route('curriculum_versions.index', [
        'search' => 'FILTER',
        'program_id' => $program->id,
        'sort' => 'version_code',
        'direction' => 'asc',
        'per_page' => 15,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CurriculumVersions/Index')
            ->where('filters.program_id', (string) $program->id)
            ->where('filters.sort', 'version_code')
            ->where('filters.direction', 'asc')
            ->where('curriculumVersions.data.0.id', $version->id));

    $this->get(route('curriculum_versions.index', ['sort' => 'invalid', 'per_page' => 101]))
        ->assertInvalid(['sort', 'per_page']);
});

it('uses the flat Curriculum Unit filter contract and rejects unsupported filters', function (): void {
    $this->withoutMiddleware(Authorize::class);
    $version = CurriculumVersion::factory()->create();
    $matching = CurriculumUnit::factory()->create(['curriculum_version_id' => $version->id, 'unit_scope' => 'program']);
    CurriculumUnit::factory()->create(['unit_scope' => 'common']);

    $this->get(route('curriculum_unit.index', [
        'curriculum_version_id' => $version->id,
        'unit_scope' => 'program',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CurriculumUnits/Index')
            ->where('filters.curriculum_version_id', (string) $version->id)
            ->where('filters.unit_scope', 'program')
            ->where('curriculumUnits.data.0.id', $matching->id));

    $this->get(route('curriculum_unit.index', ['unit_scope' => 'unsupported']))
        ->assertInvalid(['unit_scope']);
});

it('renders the Catalog-owned curriculum overview summary', function (): void {
    $this->withoutMiddleware(Authorize::class);
    $version = CurriculumVersion::factory()->create(['version_code' => 'CAT-SUMMARY']);

    $this->get(route('curriculum_versions.summary.overview', $version))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CurriculumVersions/summary/Overview')
            ->where('curriculumVersion.id', $version->id)
            ->where('curriculumVersion.version_code', 'CAT-SUMMARY')
            ->where('data.totalUnits', 0));
});

it('redirects the Curriculum Version show alias to the overview page', function (): void {
    $this->withoutMiddleware(Authorize::class);
    $version = CurriculumVersion::factory()->create(['version_code' => 'CAT-SHOW']);

    $this->get(route('curriculum-versions.show', $version))
        ->assertRedirect(route('curriculum_versions.summary.overview', $version));
});

it('keeps the canonical Curriculum Version update route behind edit permission', function (): void {
    $route = app('router')->getRoutes()->getByName('curriculum_versions.update');

    expect($route)->not->toBeNull()
        ->and($route?->methods())->toContain('PUT')
        ->and($route?->gatherMiddleware())->toContain('can:edit_curriculum_version')
        ->not->toContain('can:view_curriculum_version')
        ->and(app('router')->getRoutes()->getByName('curriculum-versions.update'))->toBeNull();
});

it('renders student summary data through the Student Registry boundary', function (): void {
    $this->withoutMiddleware(Authorize::class);
    $version = CurriculumVersion::factory()->create();

    $this->get(route('curriculum_versions.summary.students', $version))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CurriculumVersions/summary/Students')
            ->where('curriculumVersion.id', $version->id)
            ->where('data.total', 0)
            ->where('data.counts.active', 0));
});
