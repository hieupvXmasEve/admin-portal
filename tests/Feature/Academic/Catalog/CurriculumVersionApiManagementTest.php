<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\User;
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
