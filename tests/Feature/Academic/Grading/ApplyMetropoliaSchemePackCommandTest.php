<?php

declare(strict_types=1);

use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Writes a mapping JSON file to a temp path and returns it. The file is cleaned
 * up after the test via Pest's afterEach below.
 */
function writeMapping(array $rows): string
{
    $path = storage_path('app/testing-metropolia-map-'.uniqid().'.json');
    file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT));

    return $path;
}

afterEach(function () {
    foreach (glob(storage_path('app/testing-metropolia-map-*.json')) ?: [] as $file) {
        @unlink($file);
    }
});

it('dry runs a metropolia mapping without mutating templates', function () {
    $unit = Unit::factory()->create(['code' => 'SW1-PROG']);
    $template = SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Programming']);

    $mappingPath = writeMapping([
        ['unit_code' => 'SW1-PROG', 'syllabus_title' => 'Programming', 'scheme_key' => 'software_1.programming'],
    ]);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect($template->fresh()->grading_scheme)->toBeNull();
});

it('commits a metropolia mapping to the selected template only', function () {
    $unit = Unit::factory()->create(['code' => 'SW1-PROG']);
    $template = SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Programming']);
    $unmapped = SyllabusTemplate::factory()->create();

    $mappingPath = writeMapping([
        ['unit_code' => 'SW1-PROG', 'syllabus_title' => 'Programming', 'scheme_key' => 'software_1.programming'],
    ]);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--commit' => true,
    ])->assertExitCode(0);

    expect($template->fresh()->grading_scheme['source_reference'])->toBe('Software 1 / Programming')
        ->and($template->fresh()->grading_scheme['engine'])->toBe('metropolia_v1')
        ->and($unmapped->fresh()->grading_scheme)->toBeNull();
});

it('fails when a mapping resolves to no template', function () {
    $mappingPath = writeMapping([
        ['unit_code' => 'MISSING', 'scheme_key' => 'software_1.programming'],
    ]);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--commit' => true,
    ])->assertExitCode(1);
});

it('fails when a mapping resolves to more than one template', function () {
    $unit = Unit::factory()->create(['code' => 'SW1-DB']);
    SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Database']);
    SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Database']);

    $template = SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Database']);

    $mappingPath = writeMapping([
        ['unit_code' => 'SW1-DB', 'syllabus_title' => 'Database', 'scheme_key' => 'software_1.database'],
    ]);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--commit' => true,
    ])->assertExitCode(1);

    // Ambiguity must abort the whole run without writing partial data.
    expect($template->fresh()->grading_scheme)->toBeNull();
});

it('requires exactly one of --dry-run or --commit', function () {
    $mappingPath = writeMapping([]);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
    ])->assertExitCode(1);

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--dry-run' => true,
        '--commit' => true,
    ])->assertExitCode(1);
});

it('fails when the mapping file is missing', function () {
    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => storage_path('app/does-not-exist.json'),
        '--dry-run' => true,
    ])->assertExitCode(1);
});
