<?php

declare(strict_types=1);

it('keeps Facilities availability off Delivery persistence and implementation types', function (): void {
    $facilitiesRoot = dirname(__DIR__, 3).'/app/Modules/Facilities';
    $forbiddenImports = [
        'App\\Models\\ClassSession',
        'App\\Models\\ExamResitSession',
        'App\\Models\\ExamRoomSlot',
        'App\\Models\\Campus',
        'App\\Modules\\Academic\\',
    ];
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($facilitiesRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        foreach ($forbiddenImports as $forbiddenImport) {
            if (str_contains($contents, $forbiddenImport)) {
                $violations[] = $file->getPathname().' uses '.$forbiddenImport;
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Facilities must use the AcademicSpaceOccupancyReader contract rather than Delivery persistence or services.',
    );
});

it('keeps Delivery exam scheduling on the Facilities reservation contract', function (): void {
    $action = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Actions/CreateExamRoomSlotAction.php') ?: '';
    $controller = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Http/Web/ExamScheduleController.php') ?: '';
    $request = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Http/Requests/ExamResit/StoreExamRoomSlotRequest.php') ?: '';

    expect($action)->toContain('App\\Shared\\Contracts\\Facilities\\SpaceReservationContract')
        ->not->toContain('App\\Models\\Room')
        ->not->toContain('App\\Modules\\Academic\\Services\\ExamScheduleConflictChecker')
        ->and($controller)->toContain('App\\Shared\\Contracts\\Facilities\\SpaceReferenceReader')
        ->not->toContain('App\\Models\\Room')
        ->and($request)->not->toContain('exists:rooms');
});

it('serves legacy building URLs from the Facilities owner', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/routes/web.php') ?: '';
    $roomRoutes = file_get_contents(dirname(__DIR__, 3).'/routes/web/rooms.php') ?: '';

    expect($routes)->toContain('App\\Modules\\Facilities\\Http\\Web\\BuildingController')
        ->not->toContain('App\\Modules\\Academic\\Http\\Web\\BuildingController')
        ->and($roomRoutes)->toContain('App\\Modules\\Facilities\\Http\\Web\\RoomController');
});
