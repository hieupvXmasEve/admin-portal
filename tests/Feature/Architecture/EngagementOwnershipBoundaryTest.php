<?php

declare(strict_types=1);

it('routes supported event and club workflows through the Engagement owner', function (): void {
    $root = base_path();

    expect($root.'/app/Modules/Engagement/routes/web.php')->toBeFile()
        ->and($root.'/app/Modules/Engagement/routes/api.php')->toBeFile()
        ->and($root.'/app/Modules/Engagement/Http/Web/EventController.php')->toBeFile()
        ->and($root.'/app/Modules/Engagement/Http/Api/Student/EventController.php')->toBeFile()
        ->and($root.'/app/Modules/Engagement/Http/Api/Student/ClubController.php')->toBeFile();

    $legacyPaths = [
        $root.'/app/Services/EventService.php',
        $root.'/app/Services/EventParticipationService.php',
        $root.'/app/Services/EventReportService.php',
        $root.'/app/Services/EventNotificationService.php',
        $root.'/app/Services/ClubService.php',
        $root.'/app/Services/ClubMembershipService.php',
        $root.'/app/Http/Controllers/EventController.php',
        $root.'/app/Http/Controllers/EventReportController.php',
        $root.'/app/Http/Controllers/Api/EventCheckinController.php',
        $root.'/app/Http/Controllers/Api/V1/EventParticipantController.php',
        $root.'/app/Http/Controllers/Web/ClubController.php',
        $root.'/app/Http/Controllers/Api/V1/Student/EventController.php',
        $root.'/app/Http/Controllers/Api/V1/Student/ClubController.php',
        $root.'/app/Http/Controllers/Api/V1/Student/ClubManagementController.php',
    ];

    foreach ($legacyPaths as $path) {
        expect($path)->not->toBeFile();
    }
});

it('keeps supported event and club runtime callers off frozen implementations', function (): void {
    $root = base_path();
    $runtimeRoots = [
        $root.'/app',
        $root.'/routes',
    ];
    $legacyReferences = [
        'App\\Services\\EventService',
        'App\\Services\\EventParticipationService',
        'App\\Services\\EventReportService',
        'App\\Services\\EventNotificationService',
        'App\\Services\\ClubService',
        'App\\Services\\ClubMembershipService',
        'App\\Http\\Controllers\\EventController',
        'App\\Http\\Controllers\\EventReportController',
        'App\\Http\\Controllers\\Api\\EventCheckinController',
        'App\\Http\\Controllers\\Api\\V1\\EventParticipantController',
        'App\\Http\\Controllers\\Web\\ClubController',
        'App\\Http\\Controllers\\Api\\V1\\Student\\EventController',
        'App\\Http\\Controllers\\Api\\V1\\Student\\ClubController',
        'App\\Http\\Controllers\\Api\\V1\\Student\\ClubManagementController',
    ];
    $violations = [];

    foreach ($runtimeRoots as $runtimeRoot) {
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
        'Supported event and club callers must use the Engagement owner boundary: '.implode(', ', $violations),
    );
});
