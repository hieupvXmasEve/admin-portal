<?php

declare(strict_types=1);

/**
 * Placement guard for the Academic-owned scholarship adjustment feature.
 *
 * The domain layer (model/services/queries/policy) already lives under
 * app/Modules/Academic. This test extends the same ownership rule to the HTTP
 * layer + routes, which no cross-import arch test covers — the gap that let
 * the controller and FormRequests originally land in the global
 * app/Http/Controllers tree (a name-match on the pre-existing global
 * ScholarshipController rather than the Academic module convention).
 */
it('keeps scholarship adjustment HTTP + routes inside the Academic module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';
    $academicRoutes = file_get_contents($workspace.'/app/Modules/Academic/routes/web.php') ?: '';

    expect($rootRoutes)
        // The feature must NOT be wired through a global route shell.
        ->not->toContain("require __DIR__.'/web/scholarship-adjustments.php';")
        ->and(file_exists($workspace.'/routes/web/scholarship-adjustments.php'))->toBeFalse()
        // It must be registered from the Academic module's own route file,
        // pointing at the module controller — never a global one.
        ->and($academicRoutes)
        ->toContain('App\\Modules\\Academic\\Http\\Web\\ScholarshipAdjustmentDossierController')
        ->not->toContain('App\\Http\\Controllers\\ScholarshipAdjustmentDossierController');

    // The controller and every FormRequest live in the module, not global.
    expect(file_exists($workspace.'/app/Modules/Academic/Http/Web/ScholarshipAdjustmentDossierController.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Http/Controllers/ScholarshipAdjustmentDossierController.php'))->toBeFalse()
        ->and(is_dir($workspace.'/app/Modules/Academic/Http/Requests/ScholarshipAdjustment'))->toBeTrue()
        ->and(is_dir($workspace.'/app/Http/Requests/ScholarshipAdjustment'))->toBeFalse();
});

/**
 * Forward-looking guard: no scholarship-adjustment HTTP class may reappear in
 * the global app/Http tree. Catches a future feature slice regressing the
 * boundary the same way (silent until this turns the CI red).
 */
it('has no scholarship adjustment controller or request under global app/Http', function (): void {
    $workspace = dirname(__DIR__, 3);

    $strays = array_merge(
        glob($workspace.'/app/Http/Controllers/**/ScholarshipAdjustment*.php') ?: [],
        glob($workspace.'/app/Http/Controllers/ScholarshipAdjustment*.php') ?: [],
        glob($workspace.'/app/Http/Requests/**/ScholarshipAdjustment*.php') ?: [],
        glob($workspace.'/app/Http/Requests/ScholarshipAdjustment/*.php') ?: [],
    );

    expect($strays)->toBeEmpty(
        'Scholarship adjustment HTTP classes are Academic-owned — place them under app/Modules/Academic/Http, not global app/Http.',
    );
});
