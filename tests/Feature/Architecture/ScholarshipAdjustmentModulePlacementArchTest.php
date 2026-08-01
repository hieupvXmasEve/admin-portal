<?php

declare(strict_types=1);

/**
 * Placement guard for the Academic-owned scholarship adjustment feature.
 *
 * Progression is the domain owner (sibling of StudentDecision/WarningCenter —
 * "decisions on a student's academic standing"). Every layer — model, query,
 * policy, actions, HTTP — lives under app/Modules/Academic/Progression, and
 * no copy is allowed to reappear at Academic top-level or under global
 * app/Http, the gap that let the controller and FormRequests originally land
 * in the global app/Http/Controllers tree.
 */
it('keeps scholarship adjustment HTTP + routes inside the Academic Progression sub-module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $rootRoutes = file_get_contents($workspace.'/routes/web.php') ?: '';
    $academicRoutes = file_get_contents($workspace.'/app/Modules/Academic/routes/web.php') ?: '';

    expect($rootRoutes)
        // The feature must NOT be wired through a global route shell.
        ->not->toContain("require __DIR__.'/web/scholarship-adjustments.php';")
        ->and(file_exists($workspace.'/routes/web/scholarship-adjustments.php'))->toBeFalse()
        // It must be registered from the Academic module's own route file,
        // pointing at the Progression controller — never a global one.
        ->and($academicRoutes)
        ->toContain('App\\Modules\\Academic\\Progression\\Http\\Web\\ScholarshipAdjustmentDossierController')
        ->not->toContain('App\\Http\\Controllers\\ScholarshipAdjustmentDossierController')
        ->not->toContain('App\\Modules\\Academic\\Http\\Web\\ScholarshipAdjustmentDossierController');

    // The controller and every FormRequest live in Progression, not global.
    expect(file_exists($workspace.'/app/Modules/Academic/Progression/Http/Web/ScholarshipAdjustmentDossierController.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Http/Controllers/ScholarshipAdjustmentDossierController.php'))->toBeFalse()
        ->and(is_dir($workspace.'/app/Modules/Academic/Progression/Http/Requests/ScholarshipAdjustment'))->toBeTrue()
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
        'Scholarship adjustment HTTP classes are Academic-owned — place them under app/Modules/Academic/Progression/Http, not global app/Http.',
    );
});

/**
 * The domain layer (model/query/policy/actions) must live under
 * Academic\Progression — not at Academic top-level, and Services/ must stay
 * gone (Progression has no Services dir; the three former services became
 * Actions).
 */
it('keeps the scholarship adjustment domain layer inside Academic Progression', function (): void {
    $workspace = dirname(__DIR__, 3);

    expect(file_exists($workspace.'/app/Modules/Academic/Progression/Models/ScholarshipAdjustmentDossier.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Queries/ScholarshipAdjustmentCandidateQuery.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Policies/ScholarshipAdjustmentDossierPolicy.php'))->toBeTrue()
        ->and(is_dir($workspace.'/app/Modules/Academic/Progression/Actions/ScholarshipAdjustment'))->toBeTrue();

    $strayServices = glob($workspace.'/app/Modules/Academic/Services/ScholarshipAdjustment*.php') ?: [];
    $strayModels = glob($workspace.'/app/Modules/Academic/Models/ScholarshipAdjustment*.php') ?: [];
    $strayQueries = glob($workspace.'/app/Modules/Academic/Queries/ScholarshipAdjustment*.php') ?: [];
    $strayPolicies = glob($workspace.'/app/Modules/Academic/Policies/ScholarshipAdjustment*.php') ?: [];
    $strayHttp = array_merge(
        glob($workspace.'/app/Modules/Academic/Http/Web/ScholarshipAdjustment*.php') ?: [],
        glob($workspace.'/app/Modules/Academic/Http/Requests/ScholarshipAdjustment/*.php') ?: [],
    );

    expect($strayServices)->toBeEmpty('ScholarshipAdjustment services must not remain under app/Modules/Academic/Services — they became Progression Actions.')
        ->and($strayModels)->toBeEmpty('ScholarshipAdjustment model must live under Academic/Progression/Models, not Academic top-level.')
        ->and($strayQueries)->toBeEmpty('ScholarshipAdjustment query must live under Academic/Progression/Queries, not Academic top-level.')
        ->and($strayPolicies)->toBeEmpty('ScholarshipAdjustment policy must live under Academic/Progression/Policies, not Academic top-level.')
        ->and($strayHttp)->toBeEmpty('ScholarshipAdjustment HTTP classes must live under Academic/Progression/Http, not Academic top-level.');
});

/**
 * P4: the student-portal confirmation controller is a domain-owned student API
 * — it belongs in the Progression module's Http/Api/Student (sibling of
 * AcademicRecordController), NOT the global app/Http/Controllers/Api tree.
 * The confirmation Actions live with the rest of the domain in Progression.
 */
it('keeps the P4 confirmation HTTP + actions inside Academic Progression', function (): void {
    $workspace = dirname(__DIR__, 3);

    expect(file_exists($workspace.'/app/Modules/Academic/Progression/Http/Api/Student/ScholarshipAdjustmentConfirmationController.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/RequestConfirmationAction.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/RecordStudentResponseAction.php'))->toBeTrue()
        ->and(file_exists($workspace.'/app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/MarkOverdueAction.php'))->toBeTrue();

    // No scholarship-adjustment student controller/request leaked into the
    // global student-portal surface.
    $strayGlobal = array_merge(
        glob($workspace.'/app/Http/Controllers/Api/V1/Student/ScholarshipAdjustment*.php') ?: [],
        glob($workspace.'/app/Http/Requests/Api/V1/Student/*ScholarshipAdjustment*.php') ?: [],
    );

    expect($strayGlobal)->toBeEmpty('ScholarshipAdjustment student API is Academic-owned — place it under Academic/Progression/Http/Api/Student, not the global Api/V1/Student surface.');
});
