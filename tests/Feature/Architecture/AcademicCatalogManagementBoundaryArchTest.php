<?php

declare(strict_types=1);

it('routes program management through the Academic Catalog owner', function (): void {
    $workspace = dirname(__DIR__, 3);
    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';
    $catalogRoutes = file_get_contents($workspace.'/app/Modules/Academic/Catalog/routes/web.php') ?: '';

    expect($rootRoutes)
        ->not->toContain("require __DIR__.'/web/programs.php';")
        ->and($catalogRoutes)
        ->toContain('App\\Modules\\Academic\\Catalog\\Http\\Web\\ProgramController')
        ->not->toContain('App\\Http\\Controllers\\Web\\ProgramController');
});

it('retired Unit and Program shells have no remaining live owner', function (): void {
    $workspace = dirname(__DIR__, 3);
    $catalogActions = glob($workspace.'/app/Modules/Academic/Catalog/Actions/*Unit*Action.php') ?: [];

    expect($catalogActions)
        ->not->toBeEmpty()
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/UnitController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/ProgramController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Services/UnitExcelExportService.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Services/UnitExcelImportService.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Services/ProgramService.php'))->toBeFalse()
        ->and(file_exists($workspace.'/routes/web/units.php'))->toBeFalse()
        ->and(file_exists($workspace.'/routes/web/programs.php'))->toBeFalse();
});

it('routes assessment definition writes through the Delivery contract', function (): void {
    $workspace = dirname(__DIR__, 3);
    $catalogPaths = [
        '/app/Modules/Academic/Catalog/Actions/CreateSyllabusTemplateAction.php',
        '/app/Modules/Academic/Catalog/Actions/UpdateSyllabusTemplateAction.php',
        '/app/Modules/Academic/Catalog/Support/UnitSpreadsheetImporter.php',
    ];
    $provider = file_get_contents($workspace.'/app/Modules/Academic/Providers/AcademicServiceProvider.php') ?: '';

    foreach ($catalogPaths as $path) {
        $contents = file_get_contents($workspace.$path) ?: '';

        expect($contents)
            ->toContain('AssessmentDefinitionWriter')
            ->not->toContain('App\\Models\\AssessmentComponent')
            ->not->toContain('App\\Models\\AssessmentComponentDetail');
    }

    expect($provider)
        ->toContain('AssessmentDefinitionWriter::class, DeliveryAssessmentDefinitionWriter::class');
});

it('keeps selected academic period changes inside Academic Catalog', function (): void {
    $workspace = dirname(__DIR__, 3);
    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';
    $catalogRoutes = file_get_contents($workspace.'/app/Modules/Academic/Catalog/routes/web.php') ?: '';
    $financeRoutes = file_get_contents($workspace.'/app/Modules/Finance/routes/web.php') ?: '';

    expect($rootRoutes)
        ->not->toContain('SemesterContextController')
        ->and($catalogRoutes)
        ->toContain('SelectedAcademicPeriodController')
        ->and($financeRoutes)
        ->not->toContain('semester-context')
        ->not->toContain('App\\Modules\\Academic\\Catalog\\Http\\Web\\SelectedAcademicPeriodController')
        ->not->toContain('App\\Http\\Controllers\\Web\\SemesterContextController');
});

it('keeps Curriculum and Syllabus HTTP shells inside Academic Catalog', function (): void {
    $workspace = dirname(__DIR__, 3);
    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';
    $catalogRoutes = file_get_contents($workspace.'/app/Modules/Academic/Catalog/routes/web.php') ?: '';

    expect($rootRoutes)
        ->not->toContain("require __DIR__.'/web/curriculum.php';")
        ->not->toContain('Route::resource(\'curriculum-versions\'')
        ->and($catalogRoutes)
        ->toContain("require __DIR__.'/curriculum.php';")
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/CurriculumVersionController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/CurriculumUnitController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/CurriculumModuleController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Http/Controllers/Web/SyllabusTemplateController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/routes/web/curriculum.php'))->toBeFalse()
        ->and(file_exists($workspace.'/routes/web/syllabus-templates.php'))->toBeFalse();
});
