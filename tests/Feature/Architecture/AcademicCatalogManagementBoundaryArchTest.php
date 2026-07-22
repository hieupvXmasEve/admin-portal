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
