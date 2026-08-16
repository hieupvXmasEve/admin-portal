<?php

declare(strict_types=1);

use App\Constants\CurriculumRoutes;
use App\Models\Campus;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
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
        Authorize::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    actingAs($this->user);
});

it('duplicates a curriculum version onto an explicitly chosen semester', function (): void {
    $program = Program::factory()->create();
    $originalSemester = Semester::factory()->active()->create();
    $targetSemester = Semester::factory()->active()->create();
    $original = CurriculumVersion::factory()->forProgram($program)->create([
        'version_code' => 'ORIG-2028',
        'semester_id' => $originalSemester->id,
    ]);
    CurriculumUnit::factory()->for($original)->create();

    $this->post(route(CurriculumRoutes::VERSION_DUPLICATE, $original), [
        'version_code' => 'DUP-2028',
        'semester_id' => $targetSemester->id,
        'include_curriculum_units' => true,
    ])->assertRedirect();

    $duplicate = CurriculumVersion::query()->where('version_code', 'DUP-2028')->firstOrFail();

    expect($duplicate->semester_id)->toBe($targetSemester->id)
        ->and($duplicate->semester_id)->not->toBe($originalSemester->id)
        ->and($duplicate->curriculumUnits()->count())->toBe(1);
});

it('falls back to the original semester when none is supplied', function (): void {
    $program = Program::factory()->create();
    $originalSemester = Semester::factory()->create();
    $original = CurriculumVersion::factory()->forProgram($program)->create([
        'version_code' => 'ORIG-2029',
        'semester_id' => $originalSemester->id,
    ]);

    $this->post(route(CurriculumRoutes::VERSION_DUPLICATE, $original), [
        'version_code' => 'DUP-2029',
        'include_curriculum_units' => false,
    ]);

    $duplicate = CurriculumVersion::query()->where('version_code', 'DUP-2029')->firstOrFail();

    expect($duplicate->semester_id)->toBe($originalSemester->id);
});

it('rejects a nonexistent semester_id', function (): void {
    $program = Program::factory()->create();
    $original = CurriculumVersion::factory()->forProgram($program)->create(['version_code' => 'ORIG-2030']);

    $this->post(route(CurriculumRoutes::VERSION_DUPLICATE, $original), [
        'version_code' => 'DUP-2030',
        'semester_id' => 999999,
        'include_curriculum_units' => false,
    ])->assertSessionHasErrors('semester_id');

    expect(CurriculumVersion::query()->where('version_code', 'DUP-2030')->exists())->toBeFalse();
});

it('rejects duplicating onto an archived semester', function (): void {
    $program = Program::factory()->create();
    $archivedSemester = Semester::factory()->archived()->create();
    $original = CurriculumVersion::factory()->forProgram($program)->create(['version_code' => 'ORIG-2031']);

    $this->post(route(CurriculumRoutes::VERSION_DUPLICATE, $original), [
        'version_code' => 'DUP-2031',
        'semester_id' => $archivedSemester->id,
        'include_curriculum_units' => false,
    ])->assertSessionHasErrors('semester_id');

    expect(CurriculumVersion::query()->where('version_code', 'DUP-2031')->exists())->toBeFalse();
});
