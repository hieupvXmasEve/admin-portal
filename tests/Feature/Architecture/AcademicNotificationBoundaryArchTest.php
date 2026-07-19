<?php

declare(strict_types=1);

arch('Academic module publishes events without importing Notification internals')
    ->expect('App\Modules\Academic')
    ->not->toUse('App\Modules\Notification');

it('keeps legacy Academic lifecycle services off Notification implementation types', function () {
    $paths = [
        ...iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Modules/Academic'))),
        dirname(__DIR__, 3).'/app/Services/CourseCompletionService.php',
        dirname(__DIR__, 3).'/app/Modules/Academic/Progression/Actions/ProcessEgcCourseResultsAction.php',
    ];

    $violations = array_values(array_filter($paths, function (mixed $path): bool {
        if ($path instanceof SplFileInfo) {
            if (! $path->isFile() || $path->getExtension() !== 'php') {
                return false;
            }

            $path = $path->getPathname();
        }

        $contents = file_get_contents($path) ?: '';

        return str_contains($contents, 'App\\Modules\\Notification\\');
    }));

    expect($violations)->toBeEmpty('Academic lifecycle producers must depend only on the shared domain-event publisher.');
});

it('detects a deliberate Academic import of a Notification implementation type', function () {
    $fixture = <<<'PHP'
    <?php
    namespace App\Modules\Academic\Actions;
    use App\Modules\Notification\Actions\PublishDomainEventAction;
    PHP;

    expect(str_contains($fixture, 'App\\Modules\\Notification\\'))->toBeTrue();
});

it('keeps EGC progression on published Course Result inputs', function () {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/app/Modules/Academic/Progression/Actions/ProcessEgcCourseResultsAction.php',
    );

    expect($contents)->not->toContain('use App\\Models\\AcademicRecord;')
        ->and($contents)->not->toContain('use App\\Models\\CourseOffering;')
        ->and($contents)->not->toContain('use App\\Services\\CourseCompletionService;');
});
