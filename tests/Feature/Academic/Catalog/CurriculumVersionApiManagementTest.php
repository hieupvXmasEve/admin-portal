<?php

declare(strict_types=1);

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
