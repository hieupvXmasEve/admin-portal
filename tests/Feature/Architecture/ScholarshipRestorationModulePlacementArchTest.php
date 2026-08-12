<?php

declare(strict_types=1);

/**
 * Placement guard for the restoration HTTP layer (Phase 2). Finance owns the
 * model/actions, so the controller/routes/FormRequests must stay inside
 * app/Modules/Finance — no stray copy under global app/Http.
 */
it('keeps scholarship restoration HTTP + routes inside the Finance module', function (): void {
    $workspace = dirname(__DIR__, 3);

    expect(file_exists($workspace.'/app/Modules/Finance/Http/Web/Admin/ScholarshipRestorationController.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Http/Controllers/ScholarshipRestorationController.php'))->toBeFalse()
        ->and(is_dir($workspace.'/app/Modules/Finance/Http/Requests/ScholarshipRestoration'))->toBeTrue()
        ->and(is_dir($workspace.'/app/Http/Requests/ScholarshipRestoration'))->toBeFalse();

    $financeRoutes = file_get_contents($workspace.'/app/Modules/Finance/routes/web.php') ?: '';
    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';

    expect($financeRoutes)
        ->toContain('App\\Modules\\Finance\\Http\\Web\\Admin\\ScholarshipRestorationController')
        ->and($rootRoutes)->not->toContain('ScholarshipRestorationController');

    $strayGlobal = array_merge(
        glob($workspace.'/app/Http/Controllers/**/ScholarshipRestoration*.php') ?: [],
        glob($workspace.'/app/Http/Requests/**/*ScholarshipRestoration*.php') ?: [],
    );

    expect($strayGlobal)->toBeEmpty('ScholarshipRestoration HTTP is Finance-owned — no global app/Http copy allowed.');
});

/**
 * Placement guard for the watchlist REPORT layer (Phase 4). Academic
 * Progression owns the report (verdict/GPA/attendance composition), so its
 * controller/export must stay inside app/Modules/Academic — no stray copy
 * under global app/Http, and the route must be registered from the Academic
 * module's own route file, never the root routes/web.php.
 */
it('keeps the scholarship restoration watchlist REPORT inside the Academic Progression module', function (): void {
    $workspace = dirname(__DIR__, 3);

    expect(file_exists($workspace.'/app/Modules/Academic/Progression/Http/Web/ScholarshipRestorationWatchlistController.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Http/Controllers/ScholarshipRestorationWatchlistController.php'))->toBeFalse()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Exports/ScholarshipRestorationWatchlistExport.php'))->toBeTrue();

    $academicRoutes = file_get_contents($workspace.'/app/Modules/Academic/routes/web.php') ?: '';
    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';

    expect($academicRoutes)
        ->toContain('App\\Modules\\Academic\\Progression\\Http\\Web\\ScholarshipRestorationWatchlistController')
        ->and($rootRoutes)->not->toContain('ScholarshipRestorationWatchlistController');

    $strayGlobal = glob($workspace.'/app/Http/Controllers/**/ScholarshipRestorationWatchlist*.php') ?: [];

    expect($strayGlobal)->toBeEmpty('ScholarshipRestorationWatchlist report HTTP is Academic-owned — no global app/Http copy allowed.');
});
