<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use App\Models\Module;
use App\Models\Unit;
use App\Shared\Contracts\Academic\CurriculumModuleCompositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes ordered module and unit composition without leaking Catalog models', function () {
    $campus = Campus::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->create();
    $module = Module::query()->create([
        'campus_id' => $campus->id,
        'code' => 'MOD-CORE',
        'name' => 'Core Module',
        'grading_type' => 'grade',
        'total_credits' => 6,
    ]);
    $firstUnit = Unit::factory()->create(['code' => 'MOD102', 'name' => 'Second Unit', 'credit_points' => 3]);
    $secondUnit = Unit::factory()->create(['code' => 'MOD101', 'name' => 'First Unit', 'credit_points' => 3]);
    $sameOrderUnit = Unit::factory()->create(['code' => 'MOD103', 'name' => 'Same-order Unit', 'credit_points' => 1]);
    $module->units()->attach($firstUnit->id, ['grading_type' => 'pass_fail', 'weight' => null, 'order' => 2]);
    $module->units()->attach($secondUnit->id, ['grading_type' => 'grade', 'weight' => 0.7, 'order' => 1]);
    $module->units()->attach($sameOrderUnit->id, ['grading_type' => 'grade', 'weight' => 0.3, 'order' => 1]);
    CurriculumModule::query()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'module_id' => $module->id,
        'year_level' => 2,
        'semester_number' => 1,
        'is_required' => true,
        'group_name' => 'Core',
        'order' => 1,
    ]);
    $omittedModule = Module::query()->create([
        'campus_id' => $campus->id,
        'code' => 'MOD-OMITTED',
        'name' => 'Omitted Module',
        'grading_type' => 'grade',
        'total_credits' => 0,
    ]);
    CurriculumModule::query()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'module_id' => $omittedModule->id,
        'order' => 2,
    ]);
    $tailModule = Module::query()->create([
        'campus_id' => $campus->id,
        'code' => 'MOD-TAIL',
        'name' => 'Tail Module',
        'grading_type' => 'pass_fail',
        'total_credits' => 0,
    ]);
    CurriculumModule::query()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'module_id' => $tailModule->id,
        'order' => 3,
    ]);
    $omittedModule->delete();

    $composition = app(CurriculumModuleCompositionReader::class)->forCurriculumVersion($curriculumVersion->id);

    expect($composition)->toHaveCount(2)
        ->and($composition[0]->moduleCode)->toBe('MOD-CORE')
        ->and($composition[0]->units)->toHaveCount(3)
        ->and($composition[0]->units[0])->toMatchArray([
            'code' => 'MOD101',
            'grading_type' => 'grade',
            'weight' => 0.7,
            'order' => 1,
        ])
        ->and($composition[0]->units[1]['grading_type'])->toBe('grade')
        ->and($composition[0]->units[1]['code'])->toBe('MOD103')
        ->and($composition[0]->units[2]['grading_type'])->toBe('pass_fail')
        ->and($composition[1]->moduleCode)->toBe('MOD-TAIL')
        ->and(array_is_list($composition))->toBeTrue();
});
