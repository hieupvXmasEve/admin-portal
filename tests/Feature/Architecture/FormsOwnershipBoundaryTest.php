<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('routes every supported forms, surveys, and query workflow through Engagement', function (): void {
    $routeNames = [
        'forms.admin.index',
        'forms.admin.runs.store',
        'forms.admin.inbox.index',
        'forms.admin.results.index',
        'forms.queries.index',
        'v1.student.forms.index',
        'v1.student.forms.query.submit',
        'v1.student.forms.surveys.submit',
        'v1.student.queries.index',
        'v1.student.queries.replies.store',
    ];

    foreach ($routeNames as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->getActionName())->toStartWith('App\\Modules\\Engagement\\');
    }
});

it('retires legacy forms and query request surfaces with no supported callers', function (): void {
    $root = base_path();
    $legacyPaths = [
        $root.'/routes/web/surveys.php',
        $root.'/app/Console/Commands/MigrateOldSurveysToFormEngine.php',
        $root.'/app/Modules/Engagement/Http/Api/Student/SurveyController.php',
        $root.'/app/Http/Requests/SubmitFormResponseRequest.php',
        $root.'/app/Http/Requests/ReviewFormResponseRequest.php',
    ];

    foreach ($legacyPaths as $path) {
        expect($path)->not->toBeFile();
    }

    $legacyReferences = [
        'App\\Http\\Requests\\SubmitFormResponseRequest',
        'App\\Http\\Requests\\ReviewFormResponseRequest',
        'App\\Console\\Commands\\MigrateOldSurveysToFormEngine',
    ];
    $violations = [];

    foreach ([$root.'/app', $root.'/routes'] as $runtimeRoot) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtimeRoot)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            foreach ($legacyReferences as $legacyReference) {
                if (str_contains($contents, $legacyReference)) {
                    $violations[] = $file->getPathname().' references '.$legacyReference;
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Supported Forms, Surveys, and Query callers must use Engagement-owned requests: '.implode(', ', $violations),
    );
});

it('owns course-offering survey provision in Engagement through the Academic context contract', function (): void {
    $controller = file_get_contents(base_path('app/Modules/Engagement/Http/Web/CourseOfferingSurveyController.php')) ?: '';
    $action = file_get_contents(base_path('app/Modules/Engagement/Actions/ProvisionCourseSurveyAction.php')) ?: '';

    expect($controller)
        ->toContain('ProvisionCourseSurveyRequest')
        ->toContain('ProvisionCourseSurveyAction')
        ->toContain('CourseOfferingSurveyContextReader')
        ->not->toContain('App\\Models\\CourseOffering');

    expect($action)
        ->toContain('CourseOfferingSurveyContext')
        ->toContain('FormTarget::query()')
        ->not->toContain('App\\Models\\CourseOffering');
});
